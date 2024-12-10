<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Contracts;

use AaronFrancis\Solo\Hotkeys\Hotkey;

interface HotkeyProvider
{
    /**
     * @return array<Hotkey>
     */
    public static function keys(): array;
}
