<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Commands;

use AaronFrancis\Solo\Facades\Solo;
use AaronFrancis\Solo\Hotkeys\Hotkey;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EnhancedTailCommand extends Command
{
    protected $hideVendor = true;

    protected string $file;

    public static function forFile($path)
    {
        return static::make('Logs', 'tail -f -n 100 ' . storage_path('logs/laravel.log'))->setFile($path);
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
                $index = $this->findNonVendorFrame($this->scrollIndex + floor($this->scrollPaneHeight() / 2));

                dd($this->wrappedLines());
                //                if ($index !== false) {
                //                    $this->lines[$index] = '___scrollpos___';
                //                }
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

    public function findNonVendorFrame(int $start)
    {
        $linesCount = count($this->lines);
        $step = 0;

        while ($start + $step < $linesCount || $start - $step >= 0) {
            // Check forward index
            if ($start + $step < $linesCount) {
                $index = $start + $step;
                if (!$this->isVendorFrame($this->lines[$index])) {
                    return $index;
                }
            }

            // Check backward index, avoiding duplicate check at step 0
            if ($step !== 0 && $start - $step >= 0) {
                $index = $start - $step;
                if (!$this->isVendorFrame($this->lines[$index])) {
                    return $index;
                }
            }

            $step++;
        }

        // If no non-vendor frames are found, return false
        return false;
    }

    public function wrapAndFormat($line): string|array
    {
        return $line;

        $theme = Solo::makeTheme();

        // A single trailing line that closes the JSON exception object.
        if (trim($line) === '"}') {
            return '';
        }

        if (str_contains($line, '{"exception":"[object] ')) {
            return $this->formatInitialException($line);
        }

        if (str_contains($line, '[stacktrace]')) {
            return '   ' . $theme->dim($line);
        }

        if (!Str::isMatch('/#[0-9]+ /', $line)) {
            return $this->wrapLine($line);
        }

        // Make the line shorter by removing the base path. Helps prevent wrapping.
        $line = str_replace(base_path(), '', $line);

        // Replace all vendor frame with a simple placeholder.
        if ($this->hideVendor && $this->isVendorFrame($line)) {
            return $theme->dim('   [Vendor frames]');
        }

        return array_map(function ($line) {
            return (Str::isMatch('/#[0-9]+ /', $line) ? str_repeat(' ', 3) : str_repeat(' ', 7)) . $line;
        }, $this->wrapLine($line, -7));
    }

    public function isVendorFrame($line)
    {
        return str_contains($line, '/vendor/') && !Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)
            || str_contains($line, '[Vendor frames]');
    }

    public function formatInitialException($line): array
    {
        $lines = explode('{"exception":"[object] ', $line);

        // Wrap first and then apply formatting, so that we don't have to
        // muck around with ANSI codes when trying to measure width.
        $message = collect($lines[0])
            ->flatMap($this->wrapLine(...))
            ->map(fn($line) => Solo::makeTheme()->red($line));

        $exception = collect($lines[1])
            // 3 for the 3 spaces we prepend.
            ->flatMap(fn($line) => $this->wrapLine($line, -3))
            ->map(fn($line) => '   ' . Solo::makeTheme()->exception($line));

        return [
            ...$message->toArray(), ...$exception->toArray()
        ];
    }

    protected function modifyWrappedLines(Collection $lines): Collection
    {
        return $lines;

        if (!$this->hideVendor) {
            return $lines;
        }

        $hasVendorFrame = false;

        // After all the lines have been wrapped, we look through them
        // to collapse consecutive vendor frames into a single line.
        return $lines->filter(function ($line) use (&$hasVendorFrame) {
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
        });
    }
}
