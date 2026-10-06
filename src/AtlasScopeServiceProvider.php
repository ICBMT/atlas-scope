<?php

declare(strict_types=1);

namespace Atlas\Scope;

use Atlas\Scope\Console\Commands\AtlasPrune;
use Atlas\Scope\Console\Commands\AtlasServe;
use Atlas\Scope\Console\Commands\ScanProjectCommand;
use Atlas\Scope\Services\Ai\AiProvider;
use Atlas\Scope\Services\Ai\OllamaClient;
use Atlas\Scope\Services\Scan\Languages\ProfileRegistry;
use Atlas\Scope\Services\Scan\ScanPipelineFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Wires AtlasScope into a host application.
 *
 * Everything the application used to declare for itself — routes, views,
 * migrations, commands, config and the two service bindings — is declared here
 * instead, because a package shares the application with its host and must
 * therefore take up the least space it can: prefixed tables, namespaced views,
 * a configurable URL prefix, and publish tags for anything a host might want to
 * own.
 */
class AtlasScopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atlas.php', 'atlas');

        // Pipelines are assembled per language by the factory: the stage list
        // lives in one place, and a scan never has to know which language it is
        // about to read — the profile decides.
        $this->app->singleton(ScanPipelineFactory::class, function ($app) {
            return new ScanPipelineFactory($app->make(ProfileRegistry::class));
        });

        // The local model runtime, behind an interface: everything that asks a
        // question goes through Atlas\Scope\Services\Ai\AiProvider, so tests
        // answer with a fake and a different runtime (LM Studio, llama.cpp) is
        // one binding away.
        $this->app->singleton(AiProvider::class, function () {
            return new OllamaClient(
                (string) config('atlas.ai.base_url'),
                (int) config('atlas.ai.timeout', 180),
                (float) config('atlas.ai.temperature', 0.2),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atlas');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanProjectCommand::class,
                AtlasPrune::class,
                AtlasServe::class,
            ]);

            $this->raiseMemoryLimit();
        }

        $this->registerPublishing();
    }

    /**
     * Scans read whole source trees, and losing a worker to a memory limit is
     * the usual cause of a scan that never finishes. Only the console is
     * touched — a web request's limit belongs to the host application.
     */
    private function raiseMemoryLimit(): void
    {
        $limit = (string) config('atlas.scan_memory_limit', '1024M');

        if ($limit !== '') {
            @ini_set('memory_limit', $limit);
            @set_time_limit(0);
        }
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // The package ships a working build, so nothing below is required to
        // run it — publishing is for hosts that want to own a piece.
        $this->publishes([
            __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
        ], 'atlas-config');

        $this->publishes([
            __DIR__.'/../assets' => public_path('vendor/atlas'),
        ], 'atlas-assets');

        // Laravel resolves error pages from resources/views/errors, which a
        // package cannot register a namespace for — so they are published into
        // the host's own error directory.
        $this->publishes([
            __DIR__.'/../resources/views/errors' => resource_path('views/errors'),
        ], 'atlas-errors');

        // The dev-server upload limits, in the one file the UI reads them from.
        $this->publishes([
            __DIR__.'/../bin/limits.env' => base_path('bin/limits.env'),
        ], 'atlas-bin');

        // For hosts that would rather build the renderer themselves.
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('vendor/atlas/js'),
            __DIR__.'/../resources/css' => resource_path('vendor/atlas/css'),
        ], 'atlas-sources');
    }
}
