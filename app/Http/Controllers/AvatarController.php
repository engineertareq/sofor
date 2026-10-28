<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves profile photos from the private media disk (Cloudflare R2 or local),
 * so who may see a photo is decided here, per request. Signed in travellers
 * see every photo; visitors only the photos of public profiles.
 */
class AvatarController extends Controller
{
    use StreamsMedia;

    public function __invoke(Request $request, string $file): StreamedResponse
    {
        $path = AvatarService::DIRECTORY.'/'.$file;

        // Only a photo somebody is currently using; replaced ones are gone.
        $owner = User::query()->where('avatar_path', $path)->first();
        abort_if($owner === null, 404);

        // Visitors who are not signed in only see photos on public profiles.
        abort_if($request->user() === null && ! $owner->hasPublicProfile(), 404);

        return $this->streamMedia(AvatarService::disk(), $path, [
            'Content-Type' => 'image/webp',
            // The file name changes with every upload, so a copy can be kept for good.
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
