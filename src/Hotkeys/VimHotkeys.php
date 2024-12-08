<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Hotkeys;

use Laravel\Prompts\Key;

class VimHotkeys extends DefaultHotkeys
{
    /*
     * In case you want VimKeys in addition to something else, you
     * can just use this function from your HotkeyProvider.
     */
    public static function remap($map)
    {
        $map['previous_tab']->remap('h');
        $map['next_tab']->remap('l');
        $map['scroll_up']->remap('k');
        $map['scroll_down']->remap('j');
        $map['page_up']->remap(Key::CTRL_U);
        $map['page_down']->remap(Key::CTRL_D);

        return $map;
    }

    /**
     * @return array<string, Hotkey>
     */
    public function keymap(): array
    {
        return static::remap(parent::keymap());
    }
}
