<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Tests\Integration;

use AaronFrancis\Solo\Commands\EnhancedTailCommand;
use AaronFrancis\Solo\Facades\Solo as SoloAlias;
use Log;
use PHPUnit\Framework\Attributes\Test;
use Str;

class BasicTest extends Base
{
    #[Test]
    public function basic_test()
    {
        $actions = [
            function (string $ansi, string $plain) {
                $this->assertStringContainsString("Stopped", $plain);
                $this->assertStringContainsString("\e[9mAbout\e[29m", $ansi);
            },
        ];

        $this->runSolo($actions, function () {
            SoloAlias::addCommands([
                'About' => 'php artisan solo:about'
            ]);
        });
    }

    #[Test]
    public function stop_command_test()
    {
        $actions = [
            // Assert that the Logs tab is not crossed out
            function (string $ansi, string $plain) {
                $this->assertStringNotContainsString("\e[9mLogs\e[29m", $ansi);
                $this->assertStringContainsString(" Running: tail ", $plain);
            },
            // Press the stop hotkey
            's',
            // Assert that the Logs tab is crossed out and it says stopped
            function (string $ansi, string $plain) {
                $this->assertStringContainsString("\e[9mLogs\e[29m", $ansi);
                $this->assertStringContainsString(" Stopped: tail ", $plain);
            },
        ];

        $this->runSolo($actions, function () {
            SoloAlias::addCommands([
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }

    #[Test]
    public function clear_output_test()
    {
        $rand = 'Testing ' . Str::random();
        Log::info($rand);

        $actions = [
            fn(string $ansi, string $plain) => $this->assertStringContainsString($rand, $ansi),
            'c',
            fn(string $ansi, string $plain) => $this->assertStringNotContainsString($rand, $ansi),
        ];

        $this->runSolo($actions, function () {
            SoloAlias::addCommands([
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }

    #[Test]
    public function tail_restarts_too_quickly()
    {
        $rand = 'Testing ' . Str::random();
        Log::info($rand);

        $actions = [
            fn(string $ansi, string $plain) => $this->assertStringContainsString($rand, $plain),
            'c',
            'r',
            1_500,
            fn(string $ansi, string $plain) => $this->assertStringNotContainsString('Waiting...', $plain),
        ];

        $this->runSolo($actions, function () {
            SoloAlias::addCommands([
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }
}
