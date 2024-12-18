<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Prompt\Dashboard;
use Chewie\Input\KeyPressListener;

class Hotkey
{
    public function __construct() {}

    public function bind(KeyPressListener $listener)
    {
        $listener->on();
    }

    public function handle(Dashboard $prompt, Command $command) {}
}
