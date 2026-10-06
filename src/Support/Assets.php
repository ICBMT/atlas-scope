<?php

declare(strict_types=1);

namespace Atlas\Scope\Support;

/**
 * URLs for the renderer's compiled bundles.
 *
 * The package ships its own build in `dist/`, so a host application needs no
 * Node toolchain. Two ways to serve it, and the published one wins:
 *
 *   1. `php artisan vendor:publish --tag=atlas-assets` copies the build into
 *      `public/vendor/atlas`, where the web server serves it directly.
 *   2. Out of the box, the files are streamed by a route instead. Slower per
 *      request, but it works on a read-only `public/` directory, on a host
 *      behind a proxy, and in a first-run demo with no publish step at all.
 */
final class Assets
{
    /** The directory the package's build lives in. */
    public static function path(string $file = ''): string
    {
        return dirname(__DIR__, 2).'/dist/'.ltrim($file, '/');
    }

    public static function url(string $file): string
    {
        $url = is_file(public_path('vendor/atlas/'.$file))
            ? asset('vendor/atlas/'.$file)
            : route('atlas.assets', ['path' => $file]);

        return $url.self::version();
    }

    /**
     * A cache-busting query string, taken from the build.
     *
     * `dist/version` is written by the package's own build script and holds a
     * short hash of the sources, so a rebuilt bundle gets a new URL and no
     * browser serves a stale one.
     */
    private static function version(): string
    {
        static $version = null;

        if ($version === null) {
            $file = self::path('version');
            $version = is_file($file) ? '?v='.trim((string) file_get_contents($file)) : '';
        }

        return $version;
    }
}
