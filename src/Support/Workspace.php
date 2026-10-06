<?php

declare(strict_types=1);

namespace Atlas\Scope\Support;

/**
 * Where a project's files live on disk.
 *
 * The scanner unpacks an upload into `{root}/{uuid}` and reads it back from
 * there for the source viewer, so the root has to be one known place. It is
 * `storage/app/atlas` unless `atlas.storage_path` says otherwise — a package
 * must not decide for the host application how its storage is laid out.
 */
final class Workspace
{
    public static function root(): string
    {
        return rtrim((string) config('atlas.storage_path', storage_path('app/atlas')), '/');
    }

    /** The `_uploads` scratch directory that chunked uploads reassemble into. */
    public static function uploads(): string
    {
        return self::root().'/_uploads';
    }

    public static function for(string $uuid): string
    {
        return self::root().'/'.$uuid;
    }

    public static function source(string $uuid): string
    {
        return self::for($uuid).'/source';
    }

    /**
     * A directory shipped inside the package — the demo project, for instance.
     * Hosts do not get to move these; they are package resources.
     */
    public static function resource(string $path): string
    {
        return dirname(__DIR__, 2).'/resources/'.trim($path, '/');
    }
}
