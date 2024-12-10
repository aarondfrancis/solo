<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Tests\Integration;

use AaronFrancis\Solo\Commands\EnhancedTailCommand;
use AaronFrancis\Solo\Commands\MakeCommand;
use AaronFrancis\Solo\Facades\Solo as SoloAlias;
use Laravel\Prompts\Key;
use Log;
use PHPUnit\Framework\Attributes\Test;

use Str;
use function Orchestra\Testbench\package_path;

class MakeTest extends Base
{
    #[Test]
    public function basic_test()
    {
        $actions = [
            'i',
            fn($plain) => $this->assertStringContainsString('Interactive', $plain),
            'Mo',
            fn($plain) => $this->assertStringContainsString('Model', $plain),
            Key::DOWN,
            Key::ENTER,
            fn($plain) => $this->assertStringContainsString('What should the model be named', $plain),
            Key::CTRL_C,
            fn($plain) => $this->assertStringContainsString('Make another class', $plain),
            fn($plain) => $this->assertStringContainsString('Exit interactive mode', $plain),
            "\x18",
            fn($plain) => $this->assertStringContainsString('Interactive mode', $plain),
        ];

        $this->runSolo($actions, function () {
            SoloAlias::addCommands([
                'Make' => MakeCommand::class,
            ]);
        });
    }
}
