<?php

declare(strict_types=1);

namespace Atlas\Scope\Http\Controllers;

use Atlas\Scope\Support\Assets;
use Atlas\Scope\Support\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the package's compiled renderer to the browser.
 *
 * Only used when the assets have not been published into `public/` (see
 * `Assets`). The path is resolved inside the package's own `assets/` directory
 * and refused if it escapes it — a request may never name a file outside the
 * build it is asking for.
 */
class AssetController
{
    private const TYPES = [
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'css' => 'text/css',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'map' => 'application/json',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'png' => 'image/png',
    ];

    public function show(string $path): Response
    {
        $file = Assets::path($path);
        $root = Assets::path();

        abort_unless(Path::isInside($file, $root) && is_file($file), 404);

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $response = new BinaryFileResponse($file);

        $response->headers->set('Content-Type', self::TYPES[$extension] ?? 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // The URL carries a build hash, so a cached copy is always the right one.
        $response->setMaxAge(31536000);
        $response->setPublic();

        return $response;
    }
}
