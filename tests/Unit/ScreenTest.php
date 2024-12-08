<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Tests\Unit;

use AaronFrancis\Solo\Support\Screen;
use PHPUnit\Framework\Attributes\Test;
use SplQueue;

class ScreenTest extends Base
{
    protected function assertEmulation(array $input, array $expect)
    {
        $screen = new Screen(180, 30);
        $screen->write(implode(PHP_EOL, $input));

        $this->assertSame(implode(PHP_EOL, $expect), $screen->output());
    }

    #[Test]
    public function clear_screen_test()
    {
        $this->assertEmulation([
            'Hello World',
            'Hello World',
            "\e[2J",
            'New Line after clear',
        ], [
            '',
            '',
            '',
            'New Line after clear',
        ]);
    }

    #[Test]
    public function colors_are_preserved()
    {
        $this->assertEmulation([
            "Hello \e[32mWorld!\e[39m how are you",
        ], [
            "Hello \e[32mWorld!\e[39m how are you",
        ]);
    }

    #[Test]
    public function laravel_prompts_make_model(): void
    {
        $this->assertEmulation([
            "\e[?25l",
            "\e[90m ┌\e[39m \e[36mWhat should the model be named?\e[39m \e[90m─────────────────────────────┐\e[39m",
            "\e[90m │\e[39m \e[2m\e[7mE\e[27m.g. Flight\e[22m                                                  \e[90m│\e[39m",
            "\e[90m └──────────────────────────────────────────────────────────────┘\e[39m",
            '',
            '',
        ], [
            '',
            "\e[90m ┌\e[39m \e[36mWhat should the model be named?\e[39m \e[90m─────────────────────────────┐\e[39m",
            "\e[90m │\e[39m \e[2;7mE\e[27m.g. Flight\e[22m                                                  \e[90m│\e[39m",
            "\e[90m └──────────────────────────────────────────────────────────────┘\e[39m",
            '',
            '',
        ]);
    }

    #[Test]
    public function test_simple_line_without_ansi_codes(): void
    {
        $this->assertEmulation([
            'Hello, World!',
            '',
            'Part two!'
        ], [
            'Hello, World!',
            '',
            'Part two!',
        ]);
    }

    #[Test]
    public function single_line_ansi_colors(): void
    {
        $this->assertEmulation([
            "\e[31mRed Text\e[0m"
        ], [
            "\e[31mRed Text\e[0m"
        ]);
    }

    #[Test]
    public function test_cursor_horizontal_absolute(): void
    {
        $this->assertEmulation([
            'Start',
            "\e[5GHello",
        ], [
            'Start',
            '    Hello', // Moved to column 5
        ]);
    }

    #[Test]
    public function test_cursor_forward(): void
    {
        // Input lines with ANSI escape code \e[5C to move cursor forward 5 columns and insert "Forward"
        $this->assertEmulation([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            // Move forward 5 columns on Line 3 and insert "Forward"
            "Line 3: Goodbye!\e[5CForward",
        ], [
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!     Forward',
        ]);
    }

    public function test_cursor_backward(): void
    {
        // Input lines with ANSI escape code \e[3D to move cursor backward 3 columns and insert "Back"
        $this->assertEmulation([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            "Line 3: Goodbye!\e[3DBack", // Move backward 3 columns on Line 3 and insert "Back"
        ], [
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: GoodbBack', // "Back" inserted starting 3 columns before the end
        ]);
    }

    #[Test]
    public function test_cursor_home(): void
    {
        // Input lines with ANSI escape code \e[H to move cursor to home and insert "Home"
        $this->assertEmulation([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            "\e[HHome", // Move cursor to home position and insert "Home"
        ], [
            'Home 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            '',
        ]);
    }

    #[Test]
    public function test_cursor_up(): void
    {
        $this->assertEmulation([
            'Line 1',
            'Line 2',
            'Line 3',
            "\e[2AInserted Line",
        ], [
            'Line 1',
            'Inserted Line',
            'Line 3',
            '',
        ]);
    }

    #[Test]
    public function test_erase_display_from_cursor_to_end(): void
    {
        // Input lines with ANSI escape code \e[0J
        $this->assertEmulation([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            // Move cursor to column 2 on Line 3
            "Line 3: Goodbye!\e[2G\e[0J",
        ], [
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'L', // 'L' is at column 1, cursor moved to column 2, so 'Line 3' becomes 'L'
        ]);
    }

    #[Test]
    public function test_erase_display_from_start_to_cursor(): void
    {
        // Input lines with ANSI escape code \e[1J
        $this->assertEmulation([
            'Line 1: Hello, World!',
            "Line 2: This is a test.\e[10G\e[1J", // Move cursor to column 10 on Line 2 and erase
            'Line 3: Goodbye!',
        ], [
            // Line 1 is cleared
            '',
            // Line 2: 10 spaces to account for clearing up to cursor, which is at column 10
            '          is is a test.',
            'Line 3: Goodbye!',
        ]);
    }

    #[Test]
    public function test_show_and_hide_cursor(): void
    {
        $this->assertEmulation([
            "\e[?25l",
            'Hidden Cursor Line',
            "\e[?25h",
            'Visible Cursor Line',
        ], [
            '',
            'Hidden Cursor Line',
            '',
            'Visible Cursor Line',
        ]);
    }

    #[Test]
    public function test_combined_ansi_codes(): void
    {
        $this->assertEmulation([
            'Line 1',
            'Line 2',
            'Line 3',
            "\e[1A\e[5GInserted", // Move up 1 line and to column 5
        ], [
            'Line 1',
            'Line 2',
            'LineInserted',
            ''
        ]);
    }

    #[Test]
    public function test_move_down_ansi_code(): void
    {
        // Input lines with ANSI escape code \e[1B and insert "Inserted Line"
        $this->assertEmulation([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            "\e[1B\e[5GInserted Line", // Move down 1 line and to column 5, then insert text
        ], [
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            '',
            '    Inserted Line', // "Inserted Line" starts at column 5 on Line 4
        ]);
    }

    #[Test]
    public function test_cursor_movement_beyond_screen_buffer(): void
    {
        // Attempt to move up 5 lines from line 2
        $this->assertEmulation([
            'Line 1',
            "\e[5AAbove Start",
        ], [
            'Above Start',
            ''
        ]);
    }

    #[Test]
    public function test_erase_in_line_1(): void
    {
        $this->assertEmulation([
            "hello world\e[4G\e[1K",
        ], [
            '    o world'
        ]);
    }

    #[Test]
    public function test_erase_in_line_2(): void
    {
        $this->assertEmulation([
            "hello world\e[4G\e[2K",
        ], [
            ''
        ]);
    }

    #[Test]
    public function test_erase_in_line_3(): void
    {
        $this->assertEmulation([
            "hello world\e[4G\e[0K",
        ], [
            'hel'
        ]);
    }

    #[Test]
    public function test_cursor_with_colors(): void
    {
        $this->assertEmulation([
            "\e[1;32mWorld\e[0m Test\e[8Dq",
        ], [
            "\e[1;32mWo\e[0mq\e[1;32mld\e[0m Test",
        ]);
    }

    #[Test]
    public function test_cursor_delete_with_colors(): void
    {
        $this->assertEmulation([
            "\e[1;32mWorld\e[0m Test\e[11G\e[1Ktest",
        ], [
            "          \e[0mtest",
        ]);
    }

    #[Test]
    public function clear_screen_from_cursor_to_end_clears_buffer(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[1C\e[0J"
        ]));

        $screen->output();

        $this->assertSame(
            [[
                32
            ], [
                32, // All characters on line one remain blue
                32,
                32,
                32,
            ], [
                32, // Only the first character on line two is blue
            ], [
                // Line three is completely blanked
            ],[
                //
            ]],
            $screen->ansi->buffer->buffer
        );
    }

    #[Test]
    public function clear_screen_from_start_to_cursor_clears_buffer(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[2C\e[1J"
        ]));

        $screen->output();

        $this->assertSame(
            [[
                //
            ], [
                // Line 1 is totally blank now
            ], [
                0,
                0,
                0,
                32, // Only the letter h are blue
            ], [
                32,
                32,
                32,
                32,
            ], [
                32
            ]],
            $screen->ansi->buffer->buffer
        );

    }

    #[Test]
    public function clear_entire_screen_clears_buffer(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[2C\e[2J"
        ]));

        $screen->output();

        $this->assertSame(
            [
                [],
                [],
                [],
                [],
                [],
            ],
            $screen->ansi->buffer->buffer
        );
    }

    #[Test]
    public function erase_in_line_0(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[0K"
        ]));

        $screen->output();

        $this->assertSame(
            [[
                32
            ], [
                32,
                32,
            ],[
                32
            ]],
            $screen->ansi->buffer->buffer
        );

    }

    #[Test]
    public function erase_in_line_1(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[1K"
        ]));

        $screen->output();

        $this->assertSame(
            [[
                32
            ], [
                0,
                0,
                0,
                32,
                32,
            ],[
                32
            ]],
            $screen->ansi->buffer->buffer
        );

    }

    #[Test]
    public function erase_in_line_2(): void
    {
        $screen = new Screen(180, 30);

        $screen->write(implode(PHP_EOL, [
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[2K"
        ]));

        $screen->output();

        $this->assertSame(
            [
                [32],
                [],
                [32]
            ],
            $screen->ansi->buffer->buffer
        );
    }

    #[Test]
    public function save_and_restore_cursor(): void
    {
        $this->assertEmulation([
            "\e7this is a test\e8haha!",
        ], [
            'haha!is a test',
        ]);
    }
}
