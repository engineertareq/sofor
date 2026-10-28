<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams stored media through the app, from the local disk or Cloudflare R2 alike. */
trait StreamsMedia
{
    /**
     * The file, or a 404 when it is not there. Asking for the size doubles as
     * the existence check, which saves R2 a round trip on every image.
     *
     * @param  array<string, string>  $headers
     */
    protected function streamMedia(string $disk, string $path, array $headers): StreamedResponse
    {
        $storage = Storage::disk($disk);

        try {
            $size = $storage->size($path);
        } catch (FilesystemException) {
            abort(404);
        }

        return $storage->response($path, null, $headers + ['Content-Length' => (string) $size]);
    }
}
