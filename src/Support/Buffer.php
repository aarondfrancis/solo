<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Support;

use ArrayAccess;
use ReturnTypeWillChange;

class Buffer implements ArrayAccess
{
    public array $buffer = [];

    public bool $usesStrings;

    public function __construct(bool $usesStrings = false)
    {
        $this->usesStrings = $usesStrings;
    }

    public function getBuffer()
    {
        return $this->buffer;
    }

    public function clear(
        int $startRow = 0,
        int $startCol = 0,
        int $endRow = PHP_INT_MAX,
        int $endCol = PHP_INT_MAX
    ): void {
        // Short-circuit if we're clearing the whole buffer.
        if ($startRow === 0 && $startCol === 0 && $endRow === PHP_INT_MAX && $endCol === PHP_INT_MAX) {
            $this->buffer = [];

            return;
        }

        $endRow = min($endRow, count($this->buffer) - 1);

        for ($row = $startRow; $row <= $endRow; $row++) {
            if (!array_key_exists($row, $this->buffer)) {
                continue;
            }
            $cols = $this->normalizeClearColumns($row, $startRow, $startCol, $endRow, $endCol);

            $line = $this->buffer[$row];
            $length = $this->rowLength($row);

            if ($cols[0] === 0 && $cols[1] === $length) {
                // Clearing an entire line. Benchmarked slightly
                // faster to just replace the entire row.
                $this->buffer[$row] = $this->usesStrings ? '' : [];
            } elseif ($cols[0] > 0 && $cols[1] === $length) {
                // Clearing from cols[0] to the end of the line.
                $this->buffer[$row] = $this->usesStrings
                    // Chop off the end of the string.
                    ? mb_substr($line, 0, $cols[0], 'UTF-8')
                    // Chop off the end of the array.
                    : array_slice($line, 0, $cols[0]);
            } else {
                // Clearing the middle of a row. Fill with either 0s or spaces.
                $this->fill(
                    ($this->usesStrings ? ' ' : 0), $row, $cols[0], $cols[1]
                );
            }
        }
    }

    public function expand($rows)
    {
        while (count($this->buffer) <= $rows) {
            $this->buffer[] = $this->usesStrings ? '' : [];
        }
    }

    public function fill(mixed $value, int $row, int $startCol, int $endCol)
    {
        $this->expand($row);

        $line = $this->buffer[$row];

        if ($this->usesStrings) {
            $replacement = str_repeat($value, $endCol - $startCol + 1);
            $beforeStart = mb_substr($line, 0, $startCol, 'UTF-8');
            $afterEnd = mb_substr($line, $endCol + 1, null, 'UTF-8');

            $this->buffer[$row] = $beforeStart . $replacement . $afterEnd;
        } else {
            $this->buffer[$row] = array_replace(
                $line, array_fill_keys(range($startCol, $endCol), $value)
            );
        }
    }

    public function rowLength($row)
    {
        $line = $this->buffer[$row];

        return $this->usesStrings ? mb_strlen($line, 'UTF-8') : count($line) - 1;
    }

    protected function normalizeClearColumns(int $currentRow, int $startRow, int $startCol, int $endRow, int $endCol)
    {
        if ($startRow === $endRow) {
            $cols = [$startCol, $endCol];
        } elseif ($currentRow === $startRow) {
            $cols = [$startCol, PHP_INT_MAX];
        } elseif ($currentRow === $endRow) {
            $cols = [0, $endCol];
        } else {
            $cols = [0, PHP_INT_MAX];
        }

        return [
            max($cols[0], 0),
            min($cols[1], $this->rowLength($currentRow)),
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->buffer[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset)
    {
        return $this->buffer[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->buffer[] = $value;
        } else {
            $this->buffer[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->buffer[$offset]);
    }
}
