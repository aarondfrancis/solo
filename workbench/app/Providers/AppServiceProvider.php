<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace App\Providers;

use AaronFrancis\Solo\Commands\EnhancedTailCommand;
use AaronFrancis\Solo\Facades\Solo;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\ServiceProvider;

use function Orchestra\Testbench\package_path;

class AppServiceProvider extends ServiceProvider
{
    public static function allowCommandsFromTest($class)
    {
        Solo::allowCommandsAddedFrom([
            $class
        ]);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        Solo::allowCommandsAddedFrom([
            \AaronFrancis\Solo\Console\Commands\Test::class
        ]);

        Solo::useTheme('dark')
            // Commands that auto start.
            ->addCommands([
//                'About' => 'php artisan solo:about',
                EnhancedTailCommand::forFile(storage_path('logs/laravel.log')),
                'Tail' => 'tail -f -n 100 ' . storage_path('logs/laravel.log'),
//                'HTTP' => implode(' ', [
//                    'php',
//                    ProcessUtils::escapeArgument(package_path('vendor', 'bin', 'testbench')),
//                    'serve'
//                ]),
            ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
