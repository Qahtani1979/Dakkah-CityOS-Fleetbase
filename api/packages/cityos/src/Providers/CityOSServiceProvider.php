<?php

namespace Fleetbase\CityOS\Providers;

use Fleetbase\Providers\CoreServiceProvider;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('CityOS cannot be loaded without `fleetbase/core-api` installed!');
}

class CityOSServiceProvider extends CoreServiceProvider
{
    public $observers = [];

    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
        $this->mergeConfigFrom(__DIR__ . '/../../config/cityos.php', 'cityos');
    }

    public function boot()
    {
        $this->registerObservers();
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->publishes([
            __DIR__ . '/../../config/cityos.php' => config_path('cityos.php'),
        ]);
    }
}
