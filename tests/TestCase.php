<?php

declare(strict_types=1);

namespace Atlas\Scope\Tests;

use Atlas\Scope\AtlasScopeServiceProvider;
use Atlas\Scope\Support\Workspace;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * A host application for the package's own tests.
 *
 * Testbench boots a real Laravel application with the provider registered, so
 * the suite exercises the package the way an installation does: routes at the
 * configured prefix, views through the `atlas::` namespace, and the package's
 * own migrations against an in-memory SQLite database.
 */
abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    private array $existingWorkspaces = [];

    /** @var array<int, string> */
    private array $existingUploads = [];

    protected function getPackageProviders($app): array
    {
        return [AtlasScopeServiceProvider::class];
    }

    /**
     * Workspaces are real directories full of unpacked fixtures. Keeping them
     * in the system temp directory means a test run never writes into the
     * vendor tree it was installed in, and one run never inherits the last
     * one's leftovers.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('atlas.storage_path', sys_get_temp_dir().'/atlas-scope-tests');
    }

    /**
     * The scanner unpacks every fixture into a project workspace and the
     * database rollback cannot delete files. Without this the suite would leave
     * a workspace behind after every single test — which is exactly how the
     * repository it came from crept past its file budget once already.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->existingWorkspaces = $this->entries(Workspace::root());
        $this->existingUploads = $this->entries(Workspace::uploads());
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->entries(Workspace::uploads()), $this->existingUploads) as $token) {
            File::deleteDirectory(Workspace::uploads().'/'.$token);
        }

        foreach (array_diff($this->entries(Workspace::root()), $this->existingWorkspaces) as $uuid) {
            if ($uuid === '_uploads') {
                continue;
            }

            File::deleteDirectory(Workspace::for($uuid));
        }

        parent::tearDown();
    }

    /**
     * Directory entries in a path, ignoring the dot entries.
     *
     * @return array<int, string>
     */
    private function entries(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        return array_values(array_diff(scandir($path) ?: [], ['.', '..']));
    }
}
