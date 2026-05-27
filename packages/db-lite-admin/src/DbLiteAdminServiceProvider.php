<?php

namespace DbLiteAdmin;

use Illuminate\Support\ServiceProvider;

class DbLiteAdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'db-lite-admin');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/db-lite-admin'),
        ], 'db-lite-admin-views');

        $this->publishes([
            __DIR__.'/../config/db-lite-admin.php' => config_path('db-lite-admin.php'),
        ], 'db-lite-admin-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db-lite-admin.php', 'db-lite-admin');
    }
}
