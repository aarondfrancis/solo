<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Commands;

use AaronFrancis\Solo\Facades\Solo;
use AaronFrancis\Solo\Hotkeys\Hotkey;
use AaronFrancis\Solo\Support\AnsiAware;
use AaronFrancis\Solo\Support\Screen;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Log;

class EnhancedTailCommand extends Command
{
    use Colors, InteractsWithStrings;

    protected bool $hideVendor = true;

    protected int $compressed = 0;

    protected ?int $pendingScrollIndex = null;

    protected string $file;

    public static function forFile($path)
    {
        return static::make('Logs', "tail -f -n 100 $path")->setFile($path);
    }

    public function setFile($path)
    {
        $this->file = $path;

        return $this;
    }

    /**
     * @return array<string, Hotkey>
     */
    public function hotkeys(): array
    {
        return [
            'vendor' => Hotkey::make('v', function () {
                if ($this->hideVendor) {
                    $lines = $this->wrappedLines();
                    $cursor = $this->scrollIndex;

                    while ($cursor >= 0) {
                        $line = $lines->get($cursor);

                        if ($count = Str::match("/#… \\[(\d+)]/", AnsiAware::plain($line))) {
                            $this->pendingScrollIndex ??= $this->scrollIndex += intval($count);
                        }

                        if ($this->isVendorFrame($line)) {
                            $this->pendingScrollIndex -= 1;
                        }

                        $cursor--;
                    }
                }

                $this->hideVendor = !$this->hideVendor;
            }),
            'truncate' => true ? null : Hotkey::make('t', function () {
                if (!$this->file) {
                    return;
                }

                // Opening in write mode truncates (or creates.)
                $handle = fopen($this->file, 'w');

                if ($handle !== false) {
                    fclose($handle);
                }

                // Clear the logs held in memory.
                $this->clear();
            })
        ];
    }

    protected function makeNewScreen()
    {
        // Disable wrapping by setting the width to 1000 characters. We'll wrap it ourselves.
        return new Screen(1000, $this->scrollPaneHeight());
    }

    protected function modifyWrappedLines(Collection $lines): Collection
    {
        $this->compressed = 0;
        $hasVendorFrame = false;

        // After all the lines have been wrapped, we look through them
        // to collapse consecutive vendor frames into a single line.
        $lines = $lines
            ->map($this->formatLogLine(...))
            ->flatten()
            ->reject(fn($line) => is_null($line));

        $remainingVendorLines = 0;

        if ($this->hideVendor) {
            $lines = $lines
                ->reverse()
                ->filter(function ($line) use (&$hasVendorFrame, &$remainingVendorLines) {
                    $isVendorFrame = $this->isVendorFrame($line);

                    if ($isVendorFrame) {
                        // Skip the line if a vendor frame has already been added.
                        if ($hasVendorFrame) {
                            return false;
                        }
                        // Otherwise, mark that a vendor frame has been added.
                        $hasVendorFrame = true;
                    } else {
                        // Reset the flag if the current line is not a vendor frame.
                        $hasVendorFrame = false;
                    }

                    return true;
                })
                ->reverse();
        }

        if (!is_null($this->pendingScrollIndex)) {
            $this->scrollIndex = $this->pendingScrollIndex;
            $this->pendingScrollIndex = null;
        }

        return $lines;
    }

    protected function formatInitialException($line): array
    {
        $lines = explode('{"exception":"[object] ', $line);
        $message = array_map(
            fn($line) => Solo::makeTheme()->exception($line),
            $this->wrapLine($lines[0])
        );

        $exception = array_map(
            fn($line) => '   ' . Solo::makeTheme()->exception($line),
            $this->wrapLine($lines[1], -3)
        );

        return [
            ...$message,
            ...$exception
        ];
    }

    protected function formatLogLine($line): null|array|string
    {
        $theme = Solo::makeTheme();

        // 1 space outside of each border.
        $traceBoxWidth = $this->scrollPaneWidth() - 2;

        // 1 border + 1 space on each side
        $traceContentWidth = $traceBoxWidth - 4;

        // A single trailing line that closes the JSON exception object.
        if (trim($line) === '"}') {
            return $theme->dim(' ╰' . str_repeat('═', $traceBoxWidth - 2) . '╯');
        }

        if (str_contains($line, '{"exception":"[object] ')) {
            return $this->formatInitialException($line);
        }

        if (str_contains($line, '[stacktrace]')) {
            return $theme->dim(' ╭─Trace' . str_repeat('─', $traceContentWidth - 4) . '╮');
        }

        if (!Str::isMatch('/#[0-9]+ /', $line)) {
            return $this->wrapLine($line);
        }

        $base = function_exists('Orchestra\Testbench\package_path') ? \Orchestra\Testbench\package_path() : base_path();

        // Make the line shorter by removing the base path. Helps prevent wrapping.
        $line = str_replace($base, '', $line);

        // Replace all vendor frame with a simple placeholder.
        if ($this->hideVendor && $this->isVendorFrame($line)) {
            $this->compressed += count($this->wrapLine($line, $traceContentWidth));

            $invisible = "\e[8m[$this->compressed]\e[28m";

            return $this->dim(' │ ' . $this->pad("#… $invisible", $traceContentWidth) . ' │ ');
        }

        // Extract the file so that we can keep that part not dim.
        $file = Str::match('/^#\d+ (.*?): /', $line);

        if ($file) {
            $line = explode($file, $line);
            $line = $theme->dim($line[0]) . $this->reset($file) . $this->dim($line[1]);
        }

        return array_map(
            fn($line) => $this->dim(' │ ') . $this->pad($line, $traceContentWidth) . $this->dim(' │ '),
            $this->wrapLine($line, $traceContentWidth)
        );
    }

    protected function isVendorFrame($line)
    {
        return
            (
                str_contains($line, '/vendor/') && !Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)
            )
            ||
            str_ends_with($line, '{main}')
            ||
            str_contains($line, '#…');
    }
}
