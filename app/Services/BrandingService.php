<?php

namespace App\Services;

use App\Support\RasterImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The platform logo and favicon. Admins upload them from the console; every
 * page, the web app manifest and the trip PDF read them from here, and fall
 * back to the built in Sofor artwork when nothing has been uploaded.
 *
 * Values live in SiteSettings, which reads them once per request.
 */
class BrandingService
{
    public const DISK = 'local';

    public const DIRECTORY = 'branding';

    /** Stored height of the logo. It renders at 44px at most, so this covers 3x screens with room to spare. */
    public const LOGO_HEIGHT = 240;

    public const LOGO_MAX_WIDTH = 1600;

    public const LOGO_MAX_KILOBYTES = 4096;

    /** Tab icon, iOS home screen, and the two sizes Android asks a web app manifest for. */
    public const FAVICON_SIZES = [32, 180, 192, 512];

    /** iOS fills transparency with black, so its icon gets a white backdrop instead. */
    public const OPAQUE_FAVICON_SIZE = 180;

    public const FAVICON_MAX_KILOBYTES = 2048;

    /** Sofor's own logo for emails, in public/, used until one is uploaded. */
    public const EMAIL_FALLBACK = 'images/email/sofor-logo.png';

    private const LOGO_KEY = 'branding.logo';

    private const FAVICON_KEY = 'branding.favicon';

    public function __construct(private readonly SiteSettings $settings) {}

    public function logoUrl(): ?string
    {
        $path = $this->value(self::LOGO_KEY);

        return $path !== null ? route('branding.asset', basename($path)) : null;
    }

    /**
     * The logo for the top of every email: the uploaded one, or Sofor's own
     * artwork as a PNG (mail apps do not show SVG). Sized up front, because
     * some mail apps ignore CSS and draw an image at its full size otherwise.
     *
     * @return array{url: string, width: int, height: int}
     */
    public function emailLogo(int $height = 40, int $maxWidth = 240): array
    {
        $path = $this->value(self::LOGO_KEY);
        $size = $path !== null ? rescue(fn () => getimagesizefromstring((string) Storage::disk(self::DISK)->get($path)), false, false) : false;

        [$url, $size] = $size
            ? [(string) $this->logoUrl(), $size]
            : [asset(self::EMAIL_FALLBACK), getimagesize(public_path(self::EMAIL_FALLBACK))];

        // Keep the shape; a wide logo is held to the width instead.
        $width = (int) round($height * $size[0] / max(1, $size[1]));

        if ($width > $maxWidth) {
            [$width, $height] = [$maxWidth, (int) round($maxWidth * $size[1] / max(1, $size[0]))];
        }

        return ['url' => $url, 'width' => $width, 'height' => $height];
    }

    /** The logo inlined for dompdf, which cannot fetch our own routes. */
    public function logoDataUri(): ?string
    {
        $path = $this->value(self::LOGO_KEY);

        if ($path === null || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) Storage::disk(self::DISK)->get($path));
    }

    public function faviconUrl(int $size): ?string
    {
        $base = $this->value(self::FAVICON_KEY);

        return $base !== null && in_array($size, self::FAVICON_SIZES, true)
            ? route('branding.asset', basename($base).'-'.$size.'.png')
            : null;
    }

    public function hasLogo(): bool
    {
        return $this->value(self::LOGO_KEY) !== null;
    }

    public function hasFavicon(): bool
    {
        return $this->value(self::FAVICON_KEY) !== null;
    }

    /** Whether a requested file name is one of the images currently in use. */
    public function serves(string $file): bool
    {
        $logo = $this->value(self::LOGO_KEY);

        if ($logo !== null && basename($logo) === $file) {
            return true;
        }

        $favicon = $this->value(self::FAVICON_KEY);

        return $favicon !== null && collect(self::FAVICON_SIZES)
            ->contains(fn (int $size) => basename($favicon).'-'.$size.'.png' === $file);
    }

    public function replaceLogo(UploadedFile $file): void
    {
        [$source, $width, $height] = RasterImage::open((string) $file->getRealPath(), 'logo');

        // Keep the shape, scale down (never up) to the stored height.
        $scale = min(1, self::LOGO_HEIGHT / $height, self::LOGO_MAX_WIDTH / $width);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = RasterImage::canvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source);

        // Transparent margins would make the logo look smaller than the space it is given.
        $canvas = RasterImage::trimTransparent($canvas);

        // A fresh name on every upload, so browsers can cache the file forever.
        $path = self::DIRECTORY.'/logo-'.Str::random(24).'.png';
        Storage::disk(self::DISK)->put($path, RasterImage::png($canvas));

        $this->swap(self::LOGO_KEY, $path);
    }

    public function replaceFavicon(UploadedFile $file): void
    {
        [$source, $width, $height] = RasterImage::open((string) $file->getRealPath(), 'favicon');

        $base = self::DIRECTORY.'/favicon-'.Str::random(24);

        foreach (self::FAVICON_SIZES as $size) {
            $canvas = RasterImage::canvas($size, $size, $size === self::OPAQUE_FAVICON_SIZE ? [255, 255, 255] : null);

            // Fit the whole image inside the square, centred, so nothing is cut off.
            $scale = min($size / $width, $size / $height);
            $drawWidth = max(1, (int) round($width * $scale));
            $drawHeight = max(1, (int) round($height * $scale));

            imagecopyresampled(
                $canvas, $source,
                intdiv($size - $drawWidth, 2), intdiv($size - $drawHeight, 2), 0, 0,
                $drawWidth, $drawHeight, $width, $height,
            );

            Storage::disk(self::DISK)->put($base.'-'.$size.'.png', RasterImage::png($canvas));
        }

        $this->swap(self::FAVICON_KEY, $base);
    }

    public function removeLogo(): void
    {
        $this->swap(self::LOGO_KEY, null);
    }

    public function removeFavicon(): void
    {
        $this->swap(self::FAVICON_KEY, null);
    }

    /** Point a setting at new files and delete the ones it replaced. */
    private function swap(string $key, ?string $path): void
    {
        $previous = $this->value($key);

        $this->settings->put($key, $path);

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::DISK)->delete($key === self::FAVICON_KEY
                ? array_map(fn (int $size) => $previous.'-'.$size.'.png', self::FAVICON_SIZES)
                : [$previous]);
        }
    }

    private function value(string $key): ?string
    {
        return $this->settings->get($key);
    }
}
