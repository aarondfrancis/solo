<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Commands\UnsafeCommand;
use AaronFrancis\Solo\Contracts\HotkeyProvider;
use AaronFrancis\Solo\Contracts\Theme;
use AaronFrancis\Solo\Hotkeys\DefaultHotkeys;
use AaronFrancis\Solo\Hotkeys\Hotkey;
use AaronFrancis\Solo\Prompt\Renderer;
use AaronFrancis\Solo\Themes\DarkTheme;
use AaronFrancis\Solo\Themes\LightTheme;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Prompts\Themes\Default\Renderer as PromptsRenderer;
use ReflectionClass;

class Manager
{
    /**
     * @var array<Command>
     */
    protected array $commands = [];

    protected string $theme;

    protected ?Theme $cachedTheme = null;

    protected string $renderer = Renderer::class;

    /**
     * @var array<string, class-string<Theme>>
     */
    protected array $themes = [
        'light' => LightTheme::class,
        'dark' => DarkTheme::class,
    ];

    /**
     * @var array<class-string>
     */
    protected array $commandsAllowedFrom = [];

    public function __construct()
    {
        // The only classes that are guaranteed to be fully in the user's control.
        $this->commandsAllowedFrom = [
            App::getNamespace() . 'Providers\\AppServiceProvider',
            App::getNamespace() . 'Providers\\SoloServiceProvider',
        ];
    }

    /**
     * @return array<Command>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * To ensure third-party packages cannot run scripts without permission,
     * they must allowlisted here. Any classes you add here will be
     * able to add commands, so make sure you trust them!
     *
     * @param  array<class-string>  $classes
     * @return $this
     *
     * @throws Exception
     */
    public function allowCommandsAddedFrom(array $classes): static
    {
        // Make sure this can't be called from a package,
        // because that would defeat the point.
        $this->ensureSafeConfigurationLocation(__FUNCTION__);

        $this->commandsAllowedFrom = array_merge($this->commandsAllowedFrom, $classes);

        return $this;
    }

    public function registerTheme(string $name, string $theme): static
    {
        if (!class_exists($theme)) {
            throw new InvalidArgumentException("Theme class '{$theme}' does not exist.");
        }

        $reflected = new ReflectionClass($theme);

        if (!$reflected->implementsInterface(Theme::class)) {
            throw new InvalidArgumentException("Theme class '{$theme}' must implement the SoloTheme interface.");
        }

        if ($reflected->isAbstract()) {
            throw new InvalidArgumentException("Theme class '{$theme}' is not instantiable.");
        }

        // Totally fine to overwrite one of the existing themes, since we
        // ship light and dark defaults that may not be preferable.
        $this->themes[$name] = $theme;

        return $this;
    }

    /**
     * @return array<Hotkey>
     *
     * @throws Exception
     */
    public function hotkeys(): array
    {
        $hotkeys = Config::get('solo.hotkeys') ?? DefaultHotkeys::class;

        if (!is_a($hotkeys, HotkeyProvider::class, allow_string: true)) {
            throw new InvalidArgumentException('Hotkeys must implement [' . HotkeyProvider::class . ']');
        }

        return (new $hotkeys)->keys();
    }

    public function useTheme($key): static
    {
        $this->theme = $key;
        $this->cachedTheme = null;

        return $this;
    }

    public function makeTheme(): Theme
    {
        if ($this->cachedTheme) {
            return $this->cachedTheme;
        }

        // Manually set, configuration, then just default to dark.
        $coalesced = $this->theme ?? Config::get('solo.theme') ?? 'dark';

        // They may have manually set a theme that doesn't exist.
        $theme = Arr::get($this->themes, $coalesced) ?? $this->themes['dark'];

        return $this->cachedTheme = new $theme;
    }

    public function setRenderer($renderer)
    {
        if (!is_subclass_of($renderer, PromptsRenderer::class)) {
            throw new InvalidArgumentException(
                "[$renderer] must be a subclass of [" . PromptsRenderer::class . ']'
            );
        }

        $this->renderer = $renderer;
    }

    public function getRenderer(): string
    {
        return $this->renderer;
    }

    public function addCommand(string|Command $command, ?string $name = null): static
    {
        if (is_string($command) && is_a($command, Command::class, allow_string: true)) {
            $command = app($command);
        }

        if (is_string($command)) {
            if (is_null($name)) {
                throw new InvalidArgumentException('Name must be provided when command is a string.');
            }

            $command = new Command(name: $name, command: $command);
        }

        if (!$command instanceof Command) {
            throw new InvalidArgumentException(
                '[' . get_class($command) . '] must be an instance of [' . Command::class . '].'
            );
        }

        if (!$this->registrationIsAllowed()) {
            $command = new UnsafeCommand($command->name, $command->process, false);
            $command->logCaller($this->caller());
        }

        $this->commands[] = $command;

        return $this;
    }

    public function clearCommands(): static
    {
        $this->commands = [];

        return $this;
    }

    public function addCommands(array $commands): static
    {
        foreach ($commands as $name => $command) {
            $this->addCommand($command, $name);
        }

        return $this;
    }

    public function addLazyCommand(string $command, ?string $name = null): static
    {
        return $this->addLazyCommands([
            $name => $command
        ]);
    }

    public function addLazyCommands(array $commands): static
    {
        $existing = count($this->commands);

        $this->addCommands($commands);

        for ($i = $existing; $i < count($this->commands); $i++) {
            $this->commands[$i]->lazy();
        }

        return $this;
    }

    protected function caller(): string
    {
        return collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
            ->reject(function ($frame) {
                // Ignore frames from inside this class as we're
                // bouncing around the different methods
                return Arr::get($frame, 'class') === static::class
                    // Ignore frames that are just going through the Facade
                    || Arr::get($frame, 'class') === Facade::class
                    // Or ones where there is no class, like a closure.
                    || !Arr::has($frame, 'class');
            })
            ->pluck('class')
            ->first();
    }

    protected function registrationIsAllowed(): bool
    {
        return in_array($this->caller(), $this->commandsAllowedFrom);
    }

    protected function ensureSafeConfigurationLocation($func): void
    {
        $caller = $this->caller();
        $namespace = App::getNamespace();

        if (Str::startsWith($caller, $namespace)) {
            return;
        }

        throw new Exception(
            "`$func` may only be called from the [$namespace] namespace."
        );
    }
}
