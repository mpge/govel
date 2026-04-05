<?php

namespace Mpge\Govel;

use Mpge\Govel\Console\BuildCommand;
use Mpge\Govel\Console\ListCommand;
use Mpge\Govel\Console\MakeTaskCommand;
use Mpge\Govel\Console\MakeWorkerCommand;
use Mpge\Govel\Services\GoManager;
use Illuminate\Support\ServiceProvider;

class GovelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/govel.php', 'govel');

        $this->app->singleton(GoManager::class, function ($app) {
            return new GoManager($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/govel.php' => config_path('govel.php'),
            ], 'govel-config');

            $this->commands([
                MakeTaskCommand::class,
                MakeWorkerCommand::class,
                BuildCommand::class,
                ListCommand::class,
            ]);
        }
    }
}
