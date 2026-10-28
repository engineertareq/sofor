<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Trip;
use App\Support\RasterImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Photos of bills on trip costs. Like profile photos, every upload is decoded
 * and redrawn as a WebP (at most 1600px on the long edge, still easy to read),
 * so phone EXIF - including the GPS position - never reaches the disk.
 *
 * Receipts are private media: only travellers on the trip can open them,
 * through ReceiptController.
 */
class ReceiptService
{
    public const DIRECTORY = 'receipts';

    public const MAX_EDGE = 1600;

    public const MAX_KILOBYTES = 8192;

    public const MAX_PIXELS = 25_000_000;

    public static function disk(): string
    {
        return config('media.disks.private', 'local');
    }

    /**
     * Decode and redraw an upload. Done before the cost is saved, so a photo
     * that cannot be read stops the form instead of leaving half a cost.
     */
    public function prepare(UploadedFile $file): string
    {
        return $this->render((string) $file->getRealPath());
    }

    /** Store a prepared photo on the cost. False when storage is unavailable. */
    public function attach(Expense $expense, string $bytes): bool
    {
        $path = self::DIRECTORY.'/'.$expense->trip_id.'/'.$expense->id.'-'.Str::random(20).'.webp';

        try {
            Storage::disk(self::disk())->put($path, $bytes, ['ContentType' => 'image/webp']);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $previous = $expense->receipt_path;
        $expense->forceFill(['receipt_path' => $path])->saveQuietly();

        self::discard($previous);

        return true;
    }

    public function remove(Expense $expense): void
    {
        $previous = $expense->receipt_path;
        $expense->forceFill(['receipt_path' => null])->saveQuietly();

        self::discard($previous);
    }

    /** Every receipt of a trip that is being deleted. */
    public static function discardTrip(Trip $trip): void
    {
        Expense::withTrashed()
            ->where('trip_id', $trip->id)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path')
            ->each(fn (string $path) => self::discard($path));
    }

    public static function discard(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            Storage::disk(self::disk())->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function render(string $path): string
    {
        [$source, $width, $height, $type] = RasterImage::open($path, 'receipt', self::MAX_PIXELS);

        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = RasterImage::canvas($targetWidth, $targetHeight, [255, 255, 255]);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source);

        // Scaling keeps the shape, so turning the smaller copy gives the same picture.
        if ($type === IMAGETYPE_JPEG) {
            $canvas = RasterImage::orient($canvas, $path);
        }

        return RasterImage::webp($canvas, 78);
    }
}
