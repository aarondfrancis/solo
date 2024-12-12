<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    |
    | We have provided 'light' and 'dark' themes. If you prefer to
    | register your own theme, you are free to do that via the
    | Facade: Solo::registerTheme('name', MyAwesomeTheme::class);
    |
    */
    'theme' => env('SOLO_THEME', 'dark'),

    'hotkeys' => env('SOLO_KEYBINDINGS', 'default')
];
