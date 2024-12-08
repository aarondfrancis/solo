<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Providers;

use AaronFrancis\Solo\Console\Commands\About;
use AaronFrancis\Solo\Console\Commands\Install;
use AaronFrancis\Solo\Console\Commands\Make;
use AaronFrancis\Solo\Console\Commands\Monitor;
use AaronFrancis\Solo\Console\Commands\Solo;
use AaronFrancis\Solo\Console\Commands\Test;
use AaronFrancis\Solo\Manager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class SoloServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Manager::class);
    }

    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->registerCommands();
        $this->publishProviders();
    }

    protected function registerCommands()
    {
        $this->commands([
            Monitor::class,
            Solo::class,
            Install::class,
            About::class,
            Make::class,
            Test::class,
        ]);
    }

    protected function publishProviders()
    {
        $this->publishes([
            __DIR__ . '/../Stubs/SoloServiceProvider.stub' => App::path('Providers/SoloServiceProvider.php'),
        ], 'solo-provider');

    }
}
