<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Hotkeys;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Contracts\HotkeyProvider;
use Laravel\Prompts\Key;

class DefaultHotkeys implements HotkeyProvider
{
    /**
     * @return array<string, Hotkey>
     */
    public static function keymap()
    {
        // The array keys have no meaning beyond providing a way
        // to directly address hotkeys. This is useful in the
        // VimKeys class to remap the navigation keys.
        return [
            'clear' => Hotkey::make('c', KeyHandler::Clear)
                ->label('Clear'),
            'pause' => Hotkey::make('p', KeyHandler::Pause)
                ->label('Pause')
                ->display(fn(Command $command) => !$command->paused),

            'follow' => Hotkey::make('f', KeyHandler::Follow)
                ->label('Follow')
                ->display(fn(Command $command) => $command->paused),

            'start_stop' => Hotkey::make('s', KeyHandler::StartStop)
                ->label(fn(Command $command) => $command->processRunning() ? 'Stop' : 'Start'),

            'restart' => Hotkey::make('r', KeyHandler::Restart)
                ->label('Restart'),

            'quit' => Hotkey::make(['q', Key::CTRL_C], KeyHandler::Quit)
                ->label('Quit'),

            'previous_tab' => Hotkey::make([Key::LEFT, Key::LEFT_ARROW], KeyHandler::PreviousTab)
                ->label('Previous'),

            'next_tab' => Hotkey::make([Key::RIGHT, Key::RIGHT_ARROW], KeyHandler::NextTab)
                ->label('Next'),

            'scroll_up' => Hotkey::make([Key::UP, Key::UP_ARROW], KeyHandler::ScrollUp)
                ->label('Scroll up'),

            'scroll_down' => Hotkey::make([Key::DOWN, Key::DOWN_ARROW], KeyHandler::ScrollDown)
                ->label('Scroll down'),

            'page_up' => Hotkey::make(Key::SHIFT_UP, KeyHandler::PageUp)
                ->label('Page up')
                ->hidden(),

            'page_down' => Hotkey::make(Key::SHIFT_DOWN, KeyHandler::PageDown)
                ->label('Page down')
                ->hidden(),

            'dd' => Hotkey::make('d', KeyHandler::DD)
                ->label('DD')
                ->hidden(),
        ];

    }

    /**
     * @return array<Hotkey>
     */
    public static function keys(): array
    {
        return array_values(static::keymap());
    }
}
