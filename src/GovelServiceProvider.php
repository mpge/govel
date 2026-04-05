<?php

namespace Govel\Govel;

use Govel\Govel\Services\GoManager;
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
        }
    }
}
