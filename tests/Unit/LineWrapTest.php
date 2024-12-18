<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Tests\Unit;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Support\AnsiAware;
use Laravel\Prompts\Concerns\Colors;
use PHPUnit\Framework\Attributes\Test;

class LineWrapTest extends Base
{
    use Colors;

    #[Test]
    public function line_wrap(): void
    {
        $command = new Command;

        $wrapped = $command->wrapLine('123456789', 5);

        $this->assertEquals(["12345", "6789"], $wrapped);
    }

    #[Test]
    public function line_wrap_continuation(): void
    {
        $command = new Command;

        $wrapped = $command->wrapLine('123456789', 5, 3);

        $this->assertEquals(["12345", "   67", "   89"], $wrapped);
    }

}
