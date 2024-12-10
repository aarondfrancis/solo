# Solo for Laravel
```
███████╗ ██████╗ ██╗      ██████╗ 
██╔════╝██╔═══██╗██║     ██╔═══██╗
███████╗██║   ██║██║     ██║   ██║
╚════██║██║   ██║██║     ██║   ██║
███████║╚██████╔╝███████╗╚██████╔╝
╚══════╝ ╚═════╝ ╚══════╝ ╚═════╝  (for Laravel)
```

> [!WARNING]  
> This is still beta software. Use with caution.

> [!IMPORTANT]
> This package requires ext-pcntl, so it will not work on Windows. Sorry about that.

## About 

Solo for Laravel is a package to run multiple commands at once, to aid in local development. After installing, you can open the SoloServiceProvider to add or remove commands.

You can have all the commands needed to run your application behind a single command: 

```shell
php artisan solo
```

Each command runs in its own tab in Solo.

![Screenshot](https://github.com/aarondfrancis/solo/blob/main/art/screenshot.png?raw=true)

## Credits 

Solo was developed by Aaron Francis. If you like it, please let me know!
 
- Twitter: https://twitter.com/aarondfrancis
- Website: https://aaronfrancis.com
- YouTube: https://youtube.com/@aarondfrancis
- GitHub: https://github.com/aarondfrancis/solo

This would not be possible without Joe Tannenbaum's [Laracasts course](https://laracasts.com/series/cli-experiments), his [Chewie package](https://github.com/joetannenbaum/chewie), and of course [Laravel Prompts](https://laravel.com/docs/11.x/prompts).

## Installation

Require the package:

```shell
composer require aaronfrancis/solo --dev
```

Install the Service Provider:
```shell
php artisan solo:install
```

## Usage

You can run Solo with the following command:

```shell
php artisan solo
```

This will start every command defined in your `SoloServiceProvider`.


## Customization

To customize Solo, you can open your `SoloServiceProvider` and make changes there.

By default, it will look something like this:

```php
namespace App\Providers;

use AaronFrancis\Solo\Commands\EnhancedTailCommand;
use AaronFrancis\Solo\Facades\Solo;
use Illuminate\Support\ServiceProvider;

class SoloServiceProvider extends ServiceProvider
{
    public function register()
    {
        Solo::useTheme('dark')
            // Commands that auto start.
            ->addCommands([
                EnhancedTailCommand::make('Logs', 'tail -f -n 100 ' . storage_path('logs/laravel.log')),
                'Vite' => 'npm run dev',
                // 'HTTP' => 'php artisan serve',
                new Command(name: 'Foo', command: 'pwd"', autostart: false, customHotKeys: []),
                'About' => 'php artisan solo:about'
            ])
            // Not auto-started
            ->addLazyCommands([
                'Queue' => 'php artisan queue:listen --tries=1',
                // 'Reverb' => 'php artisan reverb:start',
                // 'Pint' => 'pint --ansi',
            ])
            // FQCNs of trusted classes that can add commands.
            ->allowCommandsAddedFrom([
                //
            ]);
    }

    public function boot()
    {
        //
    }
}
```

Several commands are provided to get you started in the right direction.

## Adding / removing commands

To add new commands, you can pass a key/value pair of name/command to `addCommands` or `addLazyCommands`.

Lazy commands do not auto start. That can be helpful when you don't need to run a command everytime, but it might be useful from time to time. Like Queues or Reverb.

You may also pass a `AaronFrancis\Solo\Commands\Command` instance (with no key) to the `addCommands` or `addLazyCommands` methods.

For example, notice the `EnhancedTailCommand` command here:

```php
Solo::useTheme('dark')
    // Commands that auto start.
    ->addCommands([
        EnhancedTailCommand::make('Logs', 'tail -f -n 100 ' . storage_path('logs/laravel.log')),
        'Vite' => 'npm run dev',
        // 'HTTP' => 'php artisan serve',
        new Command(name: 'Foo', command: 'pwd"', autostart: false, customHotKeys: []),
        'About' => 'php artisan solo:about'
    ])
```

`EnhancedTailCommand` is a subclass of `Command` with a little bit of logic to make the logs more readable. You're free to create your own subclasses if you want!

To remove a command, simply delete the command. No need to create a PR to fix the stub. We've provided a reasonable set of starting commands, but the `SoloServiceProvider` lives in your application, so you have full control of it.

## Adding custom hotkeys to commands

To add custom hot keys for a command, you can pass an array of `AaronFrancis\Solo\Console\CustomHotKey` instances to the `customHotKeys` parameter of the `Command` constructor.

For example, notice the `CustomHotKey` array here:

```php
Solo::useTheme('dark')
    // Commands that auto start.
    ->addCommands([
        // ...
        new Command(name: 'Foo', command: 'echo "See hotkeys below"', autostart: true, customHotKeys: [
            new CustomHotKey(
                key: 'e',
                name: 'echoE',
                callback: fn() => Log::info('pressed "e"...'),
                when: fn(Command $command) => $command->processRunning()
            ),
            new CustomHotKey(key: 'f', name: 'echoF', callback: fn() => Log::info('pressed "f"...')),
        ]),
    ])
```

The `when` parameter is optional. If provided, the hot key will only be active when the `when` callback returns `true`. This can be useful for hot keys that only make sense when a process is running.

## Usage

To use Solo, you simply need to run `php artisan solo`.

You'll be presented with a dashboard. To navigate between processes use the left/right arrows. You can scroll the output by using the up/down keys. **Shift + up/down** scrolls by 10 lines instead of one.

See the hotkeys on the dashboard for further details.

## Theming

Two themes are shipped by default: light and dark.

To change the theme you can pass 'light' or 'dark' to the `useTheme` method.

```php
Solo::useTheme('dark');
```

If you prefer to have it .env driven so that you and your teammates can have different themes, you can create a `config/solo.php` with a `theme` key. Manually set configuration takes precedence over configuration, so you'll need to remove the `useTheme` call.

### Creating a new theme

You can create a new theme by either subclassing the `LightTheme` or `DarkTheme` or by implementing the `Theme` interface. 

After you create the theme, you'll need to register it by calling:

```php
// overwrite the default light theme
Solo::registerTheme('light', AaronsAwesomeLightTheme::class);

// or create something totally new
Solo::registerTheme('synth', SynthWave::class);
```

### Modifying the dashboard

If you want, you can register a new Renderer to render the entire dashboard. This is out of scope for documentation and only for the brave of heart, but once you do you can register it thusly:

```php
Solo::setRenderer(MyFancyDashboardRenderer::class);
```

## Allowing packages to register commands

By default, packages are not allowed to register commands. If they do register commands, they'll be marked as "unsafe." They'll still show up on your dashboard, but they will not run.

To allow a package to register a command, you must add the caller to your service provider:

```php
Solo::allowCommandsAddedFrom([
  // Note that Pint doesn't actually register Solo commands, this is just an example!
  \Laravel\Pint\PintServiceProvider::class,
]);
```

## Service provider in a custom location.

By default, your `SoloServiceProvider` is created in the `App\Providers` namespace, which is pre-registered as a "safe" location to add commands from. If your `SoloServiceProvider` is in a custom location, it will still be deemed "safe" as long as it resides in your application's namespace (usually `App`, but custom root namespaces are supported.)  


## Contributing
Please help.

Also there are gonna be _so_ many edge cases with commands, terminals, etc. I need a good way to test these things. If you're good at testing, please help me set up a good scaffold. 

## Support me

This is free! I also don't take donations.

If you want to support me you can either buy one of my courses or tell your friends about them or just generally help me spread the word about the things I make. That's all!

- Mastering Postgres: https://masteringpostgres.com
- High Performance SQLite: https://highperformancesqlite.com
- Screencasting: https://screencasting.com

## FAQ

#### My command isn't working
(That's not really a question, but I'll allow it.) Does it work outside of Solo? Does it have an `--ansi`
option? Is it writing to somewhere besides `STDOUT`? Is there an option to force it to write to `STDOUT`? If
you've tried all that, feel free to open an issue.
    
#### Can I run Sail commands?
Yes! This seems to be the way to do it: `vendor/bin/sail artisan schedule:work --ansi` (Read more at #29.)
   
#### Does Solo support Windows?
It does not, sorry. Solo relies on `ext-pcntl` and a few other Linux-y things, so Windows support is not on the
roadmap.
    
#### Can I use this in production?
I wouldn't. I'd use something more robust, like supervisor or something.