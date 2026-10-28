<?php

namespace App\Services;

use App\Support\RasterImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sponsor logos: decoded, scaled down and redrawn as PNG on upload, the same
 * way the platform logo is.
 *
 * Logos are public media. On Cloudflare R2 they are served straight from the
 * bucket's public URL; on the local disk, which has no public address, they
 * go through SponsorController::logo().
 */
class SponsorLogos
{
    public const DIRECTORY = 'sponsors';

    /** Logos render at most ~96px tall on the sponsors page, so this covers 2-3x screens. */
    public const MAX_HEIGHT = 240;

    public const MAX_WIDTH = 960;

    public const MAX_KILOBYTES = 4096;

    /** Every upload gets a new file name, so any copy can be kept for good. */
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /** The disk logos are stored on. */
    public static function disk(): string
    {
        return config('media.disks.public', 'local');
    }

    /** Where a browser loads a logo from. */
    public static function url(string $path): string
    {
        return filled(config('filesystems.disks.'.self::disk().'.url'))
            ? Storage::disk(self::disk())->url($path)
            : route('sponsors.logo', basename($path));
    }

    /** @return string the stored path, relative to the disk */
    public function store(UploadedFile $file, string $attribute = 'logo'): string
    {
        [$source, $width, $height] = RasterImage::open((string) $file->getRealPath(), $attribute);

        // Keep the shape, scale down (never up).
        $scale = min(1, self::MAX_HEIGHT / $height, self::MAX_WIDTH / $width);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = RasterImage::canvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source);

        // Transparent margins would make one logo look smaller than its neighbours.
        $canvas = RasterImage::trimTransparent($canvas);

        // A fresh name on every upload, so browsers can cache the file forever.
        $path = self::DIRECTORY.'/'.Str::random(24).'.png';

        try {
            Storage::disk(self::disk())->put($path, RasterImage::png($canvas), [
                'ContentType' => 'image/png',
                'CacheControl' => self::CACHE_CONTROL,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([$attribute => __('The logo could not be saved just now. Please try again in a moment.')]);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        // A leftover file costs a few kilobytes; a failed delete must not undo the change around it.
        try {
            Storage::disk(self::disk())->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
