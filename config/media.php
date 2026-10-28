<?php

$r2 = env('MEDIA_STORAGE', 'local') === 'r2';

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage
    |--------------------------------------------------------------------------
    |
    | Where uploaded media lives: "r2" for Cloudflare R2, "local" for the
    | private local disk. The platform logo and favicon are not media in this
    | sense - they always stay on the local disk (see BrandingService).
    |
    | public:  anyone may see it, so it is served straight from the bucket's
    |          public URL (sponsor logos).
    | private: who may see it is decided per request, so it is only ever
    |          streamed through the app (profile photos).
    |
    | Existing local files are moved with `php artisan media:move-to-r2`.
    |
    */

    'storage' => $r2 ? 'r2' : 'local',

    'disks' => [
        'public' => $r2 ? 'r2' : 'local',
        // Private media needs its own, non public bucket; without one it stays local.
        'private' => $r2 && (string) env('R2_PRIVATE_BUCKET', '') !== '' ? 'r2_private' : 'local',
    ],

];
