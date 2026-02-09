<?php

namespace Fleetbase\CityOS\Providers;

use Fleetbase\Providers\CoreServiceProvider;
use Fleetbase\CityOS\Services\TemporalService;
use Fleetbase\CityOS\Services\PayloadCMSService;
use Fleetbase\CityOS\Services\ERPNextService;
use Fleetbase\CityOS\Services\CityBusService;

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

        $this->app->singleton(TemporalService::class, fn () => new TemporalService());
        $this->app->singleton(PayloadCMSService::class, fn () => new PayloadCMSService());
        $this->app->singleton(ERPNextService::class, fn () => new ERPNextService());
        $this->app->singleton(CityBusService::class, fn () => new CityBusService());
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
