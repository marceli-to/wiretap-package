<?php

namespace MarceliTo\Wiretap\Providers;

use Illuminate\Support\ServiceProvider;
use MarceliTo\Wiretap\Wiretap;

class WiretapServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('wiretap', function ($app) {
            return new Wiretap($app['config']->get('wiretap'));
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/wiretap.php' => config_path('wiretap.php'),
        ], 'wiretap-config');

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/wiretap.php',
            'wiretap'
        );
    }
}
