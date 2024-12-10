<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Support;

use InvalidArgumentException;
use RuntimeException;

class AnsiTracker
{
    /**
     * An array full of arrays full of integers. Each first-level array
     * represents a row on the screen, while each integer represents
     * the active SGR ANSI codes at a position in that line.
     */
    public Buffer $buffer;

    /**
     * The currently active SGR ANSI codes, at this moment in time.
     */
    protected int $active = 0;

    /**
     * The ANSI codes that control text foreground colors.
     *
     * @var array<array<int>>
     */
    protected array $foreground = [[30, 39], [90, 97]];

    /**
     * The ANSI codes that control text background colors.
     *
     * @var array<array<int>>
     */
    protected array $background = [[40, 49], [100, 107]];

    /**
     * The ANSI codes that control text decoration,
     * like underline, bold, italic, etc.
     *
     * @var array<array<int>>
     */
    protected array $decoration = [[1, 9]];

    /**
     * The ANSI codes that control decoration resets.
     *
     * @var array<array<int>>
     */
    protected array $resets = [[22, 29]];

    /**
     * The keys are ANSI SGR codes and the values are arbitrarily
     * assigned bit values, allowing for efficient combination
     * and manipulation using bitwise operations.
     *
     * @TODO Support
     * @link https://gist.github.com/fnky/458719343aabd01cfb17a3a4f7296797
     */
    protected array $codes = [
        // Reset all
        0 => 1 << 0, // 1

        // Foreground
        30 => 1 << 1,  // Black
        31 => 1 << 2,  // Red
        32 => 1 << 3,  // Green
        33 => 1 << 4,  // Yellow
        34 => 1 << 5,  // Blue
        35 => 1 << 6,  // Magenta
        36 => 1 << 7,  // Cyan
        37 => 1 << 8,  // White
        39 => 1 << 9,  // Default
        90 => 1 << 10, // Bright Black
        91 => 1 << 11, // Bright Red
        92 => 1 << 12, // Bright Green
        93 => 1 << 13, // Bright Yellow
        94 => 1 << 14, // Bright Blue
        95 => 1 << 15, // Bright Magenta
        96 => 1 << 16, // Bright Cyan
        97 => 1 << 17, // Bright White

        // Background
        40 => 1 << 18, // Black
        41 => 1 << 19, // Red
        42 => 1 << 20, // Green
        43 => 1 << 21, // Yellow
        44 => 1 << 22, // Blue
        45 => 1 << 23, // Magenta
        46 => 1 << 24, // Cyan
        47 => 1 << 25, // White
        49 => 1 << 26, // Default
        100 => 1 << 27, // Bright Black
        101 => 1 << 28, // Bright Red
        102 => 1 << 29, // Bright Green
        103 => 1 << 30, // Bright Yellow
        104 => 1 << 31, // Bright Blue
        105 => 1 << 32, // Bright Magenta
        106 => 1 << 33, // Bright Cyan
        107 => 1 << 34, // Bright White

        // Decoration
        1 => 1 << 35, // Set bold mode
        2 => 1 << 36, // Set dim/faint mode
        3 => 1 << 37, // Set italic mode
        4 => 1 << 38, // Set underline mode
        5 => 1 << 39, // Set blinking mode
        7 => 1 << 40, // Set inverse/reverse mode
        8 => 1 << 41, // Set hidden/invisible mode
        9 => 1 << 42, // Set strikethrough mode

        // Decoration resets
        22 => 1 << 43, // Reset bold mode AND dim/faint mode
        23 => 1 << 44, // Reset italic mode
        24 => 1 << 45, // Reset underline mode
        25 => 1 << 46, // Reset blinking mode
        27 => 1 << 47, // Reset inverse/reverse mode
        28 => 1 << 48, // Reset hidden/invisible mode
        29 => 1 << 49, // Reset strikethrough mode
    ];

    /**
     * Each key is an ANSI code that turns a certain decoration on, while
     * the value is an ANSI code that turns that decoration off.
     *
     * @var int[]
     */
    protected array $decorationResets = [
        1 => 22, // 22 does indeed turn off bold & dim.
        2 => 22, // 22 does indeed turn off bold & dim.
        3 => 23,
        4 => 24,
        5 => 25,
        7 => 27,
        8 => 28,
        9 => 29,
    ];

    public function __construct()
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('AnsiBuffer requires a 64-bit PHP environment.');
        }

        // This buffer uses integers to keep track of active ANSI codes.
        $this->buffer = new Buffer(usesStrings: false);
    }

    public function fillBufferWithActiveFlags(int $row, int $startCol, int $endCol): void
    {
        $this->buffer->fill($this->active, $row, $startCol, $endCol);
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function getActiveAsAnsi(): string
    {
        return $this->ansiStringFromBits($this->active);
    }

    public function compressedAnsiBuffer(): array
    {
        $previousBits = 0;

        $compressed = array_map(function ($line) use (&$previousBits) {
            // We return false for any duplicates, so array_filter removes those leaving us with
            // a sparsely populated array with missing numeric keys (which is what we want.)
            return array_filter(array_map(function ($bits) use (&$previousBits) {
                // Since we're compressing for output, we don't need an ANSI code for every
                // character, as they are persistent. This bitwise operation gives us
                // only the flags that weren't present in the previous bits.
                $unique = $bits & ~$previousBits;

                if ($unique) {
                    $previousBits = $bits;
                }

                return $unique ? $this->ansiStringFromBits($unique) : false;
            }, $line));
        }, $this->buffer->getBuffer());

        return $compressed;
    }

    public function ansiCodesFromBits(int $bits): array
    {
        // Short-circuit
        if ($bits === 0) {
            return [];
        }

        $active = [];

        foreach ($this->codes as $code => $bit) {
            // No point in checking further as they'll all be false.
            if ($bit > $bits) {
                break;
            }

            if (($bits & $bit) === $bit) {
                $active[] = $code;
            }
        }

        sort($active);

        return $active;
    }

    public function ansiStringFromCodes(array $codes): string
    {
        return count($codes) ? ("\e[" . implode(';', $codes) . 'm') : '';
    }

    public function ansiStringFromBits(int $bits): string
    {
        return $this->ansiStringFromCodes(
            $this->ansiCodesFromBits($bits)
        );
    }

    public function addAnsiCodes(int ...$codes)
    {
        foreach ($codes as $code) {
            $this->addAnsiCode($code);
        }
    }

    public function addAnsiCode(int $code)
    {
        if (!array_key_exists($code, $this->codes)) {
            return;
            // throw new InvalidArgumentException("Invalid ANSI code: $code");
        }

        // Reset all styles.
        if ($code === 0) {
            $this->resetBitRange(0, 64);
        }

        // If we're adding a new foreground color, zero out the old ones.
        if ($this->codeInRange($code, $this->foreground)) {
            $this->resetForeground();
        }

        // Same for backgrounds.
        if ($this->codeInRange($code, $this->background)) {
            $this->resetBackground();
        }

        // If we're adding a decoration, we need to unset the
        // code that disables that specific decoration.
        if ($this->codeInRange($code, $this->decoration)) {
            $unset = $this->codes[$this->decorationResets[$code]];
            $this->active &= ~$unset;
        }

        // If we're unsetting a decoration, we need to remove
        // the code that enables that decoration.
        if ($this->codeInRange($code, $this->resets)) {
            $unset = 0;
            foreach ($this->decorationResets as $decoration => $reset) {
                if ($code === $reset) {
                    $unset |= $this->codes[$decoration];
                }
            }
            $this->active &= ~$unset;
        }

        $this->active |= $this->codes[$code];
    }

    protected function resetForeground()
    {
        $this->resetCodeRanges($this->foreground);
    }

    protected function resetBackground()
    {
        $this->resetCodeRanges($this->background);
    }

    protected function codeInRange(int $code, array $ranges)
    {
        foreach ($ranges as $range) {
            if ($code >= $range[0] && $code <= $range[1]) {
                return true;
            }
        }

        return false;
    }

    protected function resetCodeRanges($ranges)
    {
        foreach ($ranges as $range) {
            $start = $this->codes[$range[0]];
            $end = $this->codes[$range[1]];

            $this->resetBitRange((int) log($start, 2), (int) log($end, 2));
        }
    }

    protected function resetBitRange(int $start, int $end): void
    {
        // Validate bit positions
        if ($start < 0 || $end < 0) {
            throw new InvalidArgumentException('Bit positions must be non-negative.');
        }

        if ($start > $end) {
            throw new InvalidArgumentException('Start bit must be less than or equal to end bit.');
        }

        $totalBits = 64;

        // Adjust end if it exceeds the total bits
        $end = min($end, $totalBits - 1);

        // Calculate the number of bits to clear
        $length = $end - $start + 1;

        // Handle cases where the length equals the integer size
        if ($length >= $totalBits) {
            // Clear all bits
            $this->active = 0;

            return;
        }

        // Create a mask with 1s outside the range and 0s within the range
        $mask = ~(((1 << $length) - 1) << $start);

        // Apply the mask to clear the specified bit range
        $this->active &= $mask;
    }
}
