<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Hotkeys;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Prompt\Dashboard;

enum KeyHandler
{
    // Logs
    case Clear;
    case Pause;
    case Follow;
    case ScrollUp;
    case ScrollDown;
    case PageUp;
    case PageDown;
    // Processes
    case Start;
    case Stop;
    case StartStop;
    case Restart;
    case PreviousTab;
    case NextTab;
    case Interactive;
    // Application
    case Quit;
    case DD;

    public function handler(): \Closure
    {
        return match ($this) {
            self::Clear => fn(Command $command) => $command->clear(),
            self::Pause => fn(Command $command) => $command->pause(),
            self::Follow => fn(Command $command) => $command->follow(),
            self::Restart => fn(Command $command) => $command->restart(),
            self::Stop => fn(Command $command) => $command->stop(),
            self::Start => fn(Command $command) => $command->start(),
            self::StartStop => fn(Command $command) => $command->toggle(),
            self::Quit => fn(Dashboard $prompt) => $prompt->quit(),
            self::PreviousTab => fn(Dashboard $prompt) => $prompt->previousTab(),
            self::NextTab => fn(Dashboard $prompt) => $prompt->nextTab(),
            self::ScrollUp => fn(Command $command) => $command->scrollUp(),
            self::ScrollDown => fn(Command $command) => $command->scrollDown(),
            self::PageUp => fn(Command $command) => $command->pageUp(),
            self::PageDown => fn(Command $command) => $command->pageDown(),
            self::Interactive => fn(Dashboard $prompt) => $prompt->enterInteractiveMode(),
            self::DD => fn(Command $command) => $command->dd(),
        };
    }
}
