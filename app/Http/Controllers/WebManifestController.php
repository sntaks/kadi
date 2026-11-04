<?php

namespace App\Http\Controllers;

use App\Support\WebManifest;
use Illuminate\Http\JsonResponse;

/**
 * /manifest.webmanifest, outside the `web` group (bootstrap/app.php): no session, cookies or consent and name
 * gates, and it may be cached for a few minutes like the static file it replaces.
 */
class WebManifestController
{
    public function __invoke(WebManifest $manifest): JsonResponse
    {
        return response()
            ->json($manifest->toArray(), options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
