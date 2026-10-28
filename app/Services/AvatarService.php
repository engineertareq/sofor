<?php

namespace App\Services;

use App\Models\User;
use App\Support\RasterImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Profile photos. Every upload is decoded and redrawn as WebP, so what is
 * stored is always a plain image of a known size: camera EXIF (including the
 * GPS location phones write) is dropped, and nothing hidden inside the original
 * file is carried over.
 *
 * Two files are kept per traveller: the square that is shown (avatar_path), cut
 * where they placed it in the cropper, and the whole photo scaled down
 * (avatar_original_path), so they can move or zoom the square again later
 * without uploading it again. avatar_crop remembers the square that was chosen.
 *
 * Photos are private media: they live on the private media disk (Cloudflare R2
 * or local, see config/media.php). The square is served through
 * AvatarController, which decides who may see each one; the whole photo only
 * ever goes back to its owner, for the cropper.
 */
class AvatarService
{
    public const DIRECTORY = 'avatars';

    /** Where the whole photos live, out of sight of AvatarController's file pattern. */
    public const ORIGINALS = self::DIRECTORY.'/originals';

    /** Stored edge length. Avatars render at 80px at most, so this covers 3x screens with room to spare. */
    public const SIZE = 512;

    /** Long edge of the kept whole photo: enough to zoom in 5x and still fill a 3x screen. */
    public const ORIGINAL_EDGE = 1600;

    /** How far in the cropper can zoom: the square is at least a fifth of the photo's short side. */
    public const MAX_ZOOM = 5;

    public const MAX_KILOBYTES = 8192;

    /** Decoding takes about 4 bytes a pixel; this keeps a 24 MP phone photo comfortably in memory. */
    public const MAX_PIXELS = 25_000_000;

    /** The disk photos are stored on. */
    public static function disk(): string
    {
        return config('media.disks.private', 'local');
    }

    /**
     * A new photo, cut to the square chosen in the cropper (or the centre).
     *
     * @param  array{x: float, y: float, size: float}|null  $crop  see self::square()
     */
    public function replace(User $user, UploadedFile $file, ?array $crop = null): User
    {
        $path = (string) $file->getRealPath();
        [$source, $width, $height, $type] = RasterImage::open($path, 'avatar', self::MAX_PIXELS);

        // Scale down first: turning and storing a 1600px copy is far cheaper than the full photo.
        $whole = $this->fit($source, $width, $height, self::ORIGINAL_EDGE);
        unset($source);

        // The cropper saw the photo the right way up (browsers apply the EXIF
        // turn), so the square is measured on the turned picture.
        if ($type === IMAGETYPE_JPEG) {
            $whole = RasterImage::orient($whole, $path);
        }

        $crop = self::normalise($crop, imagesx($whole), imagesy($whole));

        $saved = $this->store($user, [
            'avatar_path' => $this->render($whole, $crop),
            'avatar_original_path' => RasterImage::webp($whole, 86),
        ]);

        $this->swap($user, $saved + ['avatar_crop' => $crop]);

        return $user;
    }

    /**
     * Move or zoom the square of the photo already uploaded. Photos from before
     * the whole one was kept start from the square itself, which becomes the
     * whole photo from then on, so adjusting again never loses more detail.
     *
     * @param  array{x: float, y: float, size: float}  $crop
     */
    public function recrop(User $user, array $crop): User
    {
        $from = $user->avatar_original_path ?? $user->avatar_path;

        if ($from === null) {
            throw ValidationException::withMessages(['avatar' => __('Upload a photo first.')]);
        }

        $bytes = rescue(fn () => Storage::disk(self::disk())->get($from), null, false);
        $whole = is_string($bytes) ? @imagecreatefromstring($bytes) : false;

        if ($whole === false) {
            throw ValidationException::withMessages(['avatar' => __('Your photo could not be found. Please upload it again.')]);
        }

        $crop = self::normalise($crop, imagesx($whole), imagesy($whole));

        $files = ['avatar_path' => $this->render($whole, $crop)];

        if ($user->avatar_original_path === null) {
            $files['avatar_original_path'] = $bytes;
        }

        $this->swap($user, $this->store($user, $files) + ['avatar_crop' => $crop]);

        return $user;
    }

    public function remove(User $user): void
    {
        $this->swap($user, ['avatar_path' => null, 'avatar_original_path' => null, 'avatar_crop' => null]);
    }

    public static function discard(?string $path): void
    {
        if ($path === null) {
            return;
        }

        // A leftover file costs a few kilobytes; a failed delete must not undo the change around it.
        try {
            Storage::disk(self::disk())->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The chosen square as fractions of the whole photo: x and y are its left
     * and top edges over the width and height, size its side over the short
     * side (1 = as big as fits). Kept inside the photo and within MAX_ZOOM;
     * nothing chosen means the centre square, the way photos were always cut.
     *
     * @param  array{x?: mixed, y?: mixed, size?: mixed}|null  $crop
     * @return array{x: float, y: float, size: float}
     */
    public static function normalise(?array $crop, int $width, int $height): array
    {
        $size = min(1.0, max(1 / self::MAX_ZOOM, (float) ($crop['size'] ?? 1)));
        $side = $size * min($width, $height);

        // The room left beside the square, as a fraction of each edge.
        $roomX = ($width - $side) / $width;
        $roomY = ($height - $side) / $height;

        return [
            'x' => round(min($roomX, max(0, (float) ($crop['x'] ?? $roomX / 2))), 5),
            'y' => round(min($roomY, max(0, (float) ($crop['y'] ?? $roomY / 2))), 5),
            'size' => round($size, 5),
        ];
    }

    /** Scale an image down (never up) so its long edge is at most $edge. */
    private function fit(GdImage $source, int $width, int $height, int $edge): GdImage
    {
        $scale = min(1, $edge / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = RasterImage::canvas($w, $h);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        return $canvas;
    }

    /**
     * The chosen square as WebP, scaled down (never up) to the stored size.
     *
     * @param  array{x: float, y: float, size: float}  $crop
     */
    private function render(GdImage $whole, array $crop): string
    {
        $width = imagesx($whole);
        $height = imagesy($whole);

        $side = max(1, (int) round($crop['size'] * min($width, $height)));
        $left = min($width - $side, max(0, (int) round($crop['x'] * $width)));
        $top = min($height - $side, max(0, (int) round($crop['y'] * $height)));
        $target = min(self::SIZE, $side);

        $canvas = RasterImage::canvas($target, $target);
        imagecopyresampled($canvas, $whole, 0, 0, $left, $top, $target, $target, $side, $side);

        return RasterImage::webp($canvas);
    }

    /**
     * Write new files under fresh names, so browsers can cache each one
     * forever. If any write fails, the ones already written are taken back.
     *
     * @param  array<string, string>  $files  column => WebP bytes
     * @return array<string, string> column => stored path
     */
    private function store(User $user, array $files): array
    {
        $paths = [];

        try {
            foreach ($files as $column => $bytes) {
                $directory = $column === 'avatar_original_path' ? self::ORIGINALS : self::DIRECTORY;
                $path = $directory.'/'.$user->id.'-'.Str::random(24).'.webp';

                Storage::disk(self::disk())->put($path, $bytes, ['ContentType' => 'image/webp']);
                $paths[$column] = $path;
            }
        } catch (Throwable $e) {
            report($e);
            array_map(self::discard(...), $paths);

            throw ValidationException::withMessages(['avatar' => __('Your photo could not be saved just now. Please try again in a moment.')]);
        }

        return $paths;
    }

    /**
     * Point the account at its new files, then delete the files it no longer uses.
     *
     * @param  array<string, mixed>  $changes
     */
    private function swap(User $user, array $changes): void
    {
        $previous = array_intersect_key($user->only(['avatar_path', 'avatar_original_path']), $changes);

        $user->forceFill($changes)->save();

        foreach ($previous as $column => $path) {
            if ($path !== $user->{$column}) {
                self::discard($path);
            }
        }
    }
}
