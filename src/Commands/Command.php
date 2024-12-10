<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Commands;

use AaronFrancis\Solo\Commands\Concerns\ManagesProcess;
use AaronFrancis\Solo\Hotkeys\Hotkey;
use AaronFrancis\Solo\Hotkeys\KeyHandler;
use AaronFrancis\Solo\Support\AnsiAware;
use AaronFrancis\Solo\Support\Screen;
use Chewie\Concerns\Ticks;
use Chewie\Contracts\Loopable;
use Chewie\Input\KeyPressListener;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplQueue;

class Command implements Loopable
{
    use ManagesProcess, Ticks;

    public const MODE_PASSIVE = 1;

    public const MODE_INTERACTIVE = 2;

    public int $mode = Command::MODE_PASSIVE;

    public bool $focused = false;

    public bool $paused = false;

    public bool $interactive = false;

    public int $scrollIndex = 0;

    public SplQueue $lines;

    public int $height = 0;

    public int $width = 0;

    public Screen $screen;

    public ?KeyPressListener $keyPressListener = null;

    public function __construct(
        public ?string $name = null,
        public ?string $command = null,
        public bool $autostart = true,
    ) {
        $this->boot();
    }

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    public function boot(): void
    {
        //
    }

    public function allHotkeys(): array
    {
        // In interactive mode, the only hotkey that works is
        // Ctrl+X, to exit interactive mode. Everything else
        // gets proxied to the underlying process.
        if ($this->isInteractive()) {
            return [
                Hotkey::make("\x18", fn() => null)->label('Exit interactive mode')
            ];
        }

        $hotkeys = $this->hotkeys();

        if ($this->canBeInteractive()) {
            $hotkeys['interactive'] = Hotkey::make('i', KeyHandler::Interactive)->label('Interactive mode');
        }

        return array_filter($hotkeys);
    }

    /**
     * @return array<string, Hotkey>
     */
    public function hotkeys(): array
    {
        return [
            //
        ];
    }

    public function setDimensions($width, $height): static
    {
        $this->width = $width;
        $this->height = $height;

        $this->screen = new Screen($width, $height);

        return $this;
    }

    public function lazy(): static
    {
        $this->autostart = false;

        return $this;
    }

    public function interactive(): static
    {
        $this->interactive = true;

        return $this;
    }

    public function onTick(): void
    {
        $this->collectIncrementalOutput();

        $this->marshalProcess();
    }

    public function isFocused(): bool
    {
        return $this->focused;
    }

    public function isBlurred(): bool
    {
        return !$this->isFocused();
    }

    public function canBeInteractive(): bool
    {
        return $this->interactive;
    }

    public function isInteractive(): bool
    {
        return $this->mode === self::MODE_INTERACTIVE;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    public function dd()
    {
        dd($this->screen->buffer);
    }

    public function addOutput($text)
    {
        $this->screen->write($text);
    }

    public function addLine($line)
    {
        $this->screen->writeln($line);
    }

    public function setMode(int $mode): bool
    {
        if (!$this->interactive) {
            $mode = static::MODE_PASSIVE;
        }

        if ($this->mode === $mode) {
            return false;
        }

        $this->mode = $mode;

        return true;
    }

    public function focus(): void
    {
        $this->focused = true;
    }

    public function blur(): void
    {
        $this->focused = false;
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function follow(): void
    {
        $this->paused = false;
    }

    public function clear(): void
    {
        $this->screen = new Screen($this->width, $this->height);
    }

    public function catchUpScroll(): void
    {
        if (!$this->paused) {
            $this->scrollDown(INF);
            // `scrollDown` pauses, so turn follow back on.
            $this->follow();
        }
    }

    public function scrollDown($amount = 1): void
    {
        $this->paused = true;
        $this->scrollIndex = max(0, min(
            $this->scrollIndex + $amount,
            $this->wrappedLines()->count() - $this->scrollPaneHeight()
        ));
    }

    public function pageDown()
    {
        $this->scrollDown($this->scrollPaneHeight() - 1);
    }

    public function scrollUp($amount = 1): void
    {
        $this->paused = true;
        $this->scrollIndex = max(
            $this->scrollIndex - $amount, 0
        );
    }

    public function pageUp()
    {
        $this->scrollUp($this->scrollPaneHeight() - 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Log management
    |--------------------------------------------------------------------------
    */
    public function scrollPaneHeight(): int
    {
        // Local hotkeys
        $hotkeys = (count($this->allHotkeys()) || $this->canBeInteractive()) ? 1 : 0;

        // 5 = 1 tabs + 1 process + 1 top border + 1 bottom border + 1 global hotkeys
        return $this->height - 5 - $hotkeys;
    }

    public function scrollPaneWidth(): int
    {
        // 2 box borders + 2 spaces for padding.
        return $this->width - 4;
    }

    public function wrappedLines(): Collection
    {
        $lines = $this->screen->output();
        $lines = explode(PHP_EOL, $lines);

        return collect($lines)
            ->flatMap(function ($line) {
                return Arr::wrap($this->wrapAndFormat($line));
            })
            ->pipe(fn(Collection $lines) => $this->modifyWrappedLines($lines))
            ->values();
    }

    protected function wrapAndFormat($line): string|array
    {
        return $this->wrapLine($line);
    }

    protected function wrapLine($line, $width = null): array
    {
        $defaultWidth = $this->scrollPaneWidth();

        if (is_int($width)) {
            $width = $width < 0 ? $defaultWidth + $width : $width;
        }

        if (!$width) {
            $width = $defaultWidth;
        }

        // A bit experimental, but seems to work.
        return explode(PHP_EOL, AnsiAware::wordwrap(
            string: $line,
            width: $width,
            cut: true
        ));

        return explode(PHP_EOL, wordwrap(
            string: $line,
            width: $width,
            cut_long_words: true
        ));
    }

    protected function modifyWrappedLines(Collection $lines): Collection
    {
        // Primarily here for any subclasses.
        return $lines;
    }
}
