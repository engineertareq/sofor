<?php

namespace App\Support;

use GdImage;
use Illuminate\Validation\ValidationException;

/**
 * The few GD steps every upload goes through. Uploads are always decoded and
 * redrawn rather than stored as sent, so metadata and anything hidden in the
 * original file never reach the disk.
 */
final class RasterImage
{
    /**
     * Decode a JPG, PNG or WebP upload. Problems are reported as a validation
     * error on $attribute so the form can show them.
     *
     * @return array{0: GdImage, 1: int, 2: int, 3: int} image, width, height, IMAGETYPE_*
     */
    public static function open(string $path, string $attribute, int $maxPixels = 25_000_000): array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw self::unreadable($attribute);
        }

        [$width, $height, $type] = $info;

        if ($width * $height > $maxPixels) {
            throw ValidationException::withMessages([
                $attribute => __('That image is very large. Try one under :count megapixels.', ['count' => intdiv($maxPixels, 1_000_000)]),
            ]);
        }

        self::ensureMemory($width * $height);

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            throw self::unreadable($attribute);
        }

        return [$image, $width, $height, $type];
    }

    /** Phones store photos sideways and record the turn in EXIF; apply it. */
    public static function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) ((@exif_read_data($path) ?: [])['Orientation'] ?? 1);

        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }

    /**
     * A blank truecolor canvas: transparent, or filled with an RGB colour for
     * places that cannot show transparency (iOS home screen icons turn it black).
     *
     * @param  array{0: int, 1: int, 2: int}|null  $fill
     */
    public static function canvas(int $width, int $height, ?array $fill = null): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($fill !== null) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...$fill));
            // Draw transparent source pixels onto the fill rather than through it.
            imagealphablending($canvas, true);

            return $canvas;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    /**
     * Cut away fully transparent margins. GD's own IMG_CROP_TRANSPARENT only
     * knows palette transparency, not the alpha channel PNG exports use.
     * Meant for small, already scaled images: it looks at every pixel.
     */
    public static function trimTransparent(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$top, $bottom, $left, $right] = [$height, -1, $width, -1];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Alpha runs 0 (solid) to 127 (clear); resampling leaves faint
                // halos, so nearly clear counts as empty too.
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                    $left = min($left, $x);
                    $right = max($right, $x);
                }
            }
        }

        $untouched = $top === 0 && $left === 0 && $bottom === $height - 1 && $right === $width - 1;

        if ($bottom === -1 || $untouched) {
            return $image;
        }

        $trimmed = self::canvas($right - $left + 1, $bottom - $top + 1);
        imagecopy($trimmed, $image, 0, 0, $left, $top, $right - $left + 1, $bottom - $top + 1);

        return $trimmed;
    }

    public static function png(GdImage $image): string
    {
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    public static function webp(GdImage $image, int $quality = 82): string
    {
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }

    /** Hosting defaults (often 128M) are too tight to decode a full size phone photo. */
    private static function ensureMemory(int $pixels): void
    {
        $limit = (string) ini_get('memory_limit');

        if ($limit === '-1') {
            return;
        }

        $needed = memory_get_usage() + $pixels * 5 + 32 * 1024 * 1024;

        if (ini_parse_quantity($limit) < $needed) {
            @ini_set('memory_limit', (string) $needed);
        }
    }

    private static function unreadable(string $attribute): ValidationException
    {
        return ValidationException::withMessages([
            $attribute => __('We could not read that image. Try a JPG, PNG or WebP file.'),
        ]);
    }
}
