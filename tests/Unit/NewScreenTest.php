<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Tests\Unit;

use AaronFrancis\Solo\Support\Screen;
use Laravel\Prompts\Terminal;
use PHPUnit\Framework\Attributes\Test;
use SplQueue;

class NewScreenTest extends Base
{

    #[Test]
    public function basic_move_test()
    {
        $screen = new Screen(180, 30);

        $screen->write("Test");

        $this->assertEquals('Test', $screen->output());

        // Back 1000 characters
        $screen->write("\e[1000DBar ");

        $this->assertEquals('Bar ', $screen->output());
    }


    #[Test]
    public function simple_wrap()
    {
        $screen = new Screen(5, 30);

        $screen->write("123456789");

        $this->assertEquals("12345\n6789", $screen->output());
    }

    #[Test]
    public function wrap_overwrite()
    {
        $screen = new Screen(5, 30);

        $screen->write("\n");
        $screen->write("12345\e[1F");
        $screen->write("67890ab");

        $this->assertEquals("67890\nab345", $screen->output());
    }

    #[Test]
    public function move_up_constrained_test()
    {
        $screen = new Screen(180, 30);

        $screen->write("Test");

        $this->assertEquals('Test', $screen->output());

        // Up a thousand lines and to position 0
        $screen->write("\e[1000FBar ");

        $this->assertEquals('Bar ', $screen->output());
    }

    #[Test]
    public function carriage_return_test()
    {
        $screen = new Screen(180, 30);

        $screen->write("Test");

        $this->assertEquals('Test', $screen->output());

        // Back 1000 characters
        $screen->write("\rBar ");

        $this->assertEquals('Bar ', $screen->output());
    }


    #[Test]
    public function newline_test()
    {
        $screen = new Screen(180, 30);

        $screen->write("Test");

        $this->assertEquals('Test', $screen->output());

        // Back 1000 characters
        $screen->write("\nBar");

        $this->assertEquals("Test\nBar", $screen->output());
    }

    #[Test]
    public function trailing_newlines()
    {
        $screen = new Screen(180, 30);

        $screen->write("Test\n\n");

        $this->assertEquals("Test\n\n", $screen->output());
    }

    #[Test]
    public function cursor_remains_in_correct_location()
    {
        $screen = new Screen(180, 30);

        // Move cursor forward 5
        $screen->write("Test\n\n\e[5C");

        $this->assertEquals("Test\n\n", $screen->output());

        $screen->write("Buzz");

        $this->assertEquals("Test\n\n     Buzz", $screen->output());
    }

    #[Test]
    public function move_forward_and_write()
    {
        $screen = new Screen(180, 30);

        $screen->write("1\e[5C2");
        $screen->write('3');

        $this->assertEquals('1     23', $screen->output());
    }

    #[Test]
    public function doesnt_go_past_width_relative()
    {
        $screen = new Screen(10, 30);
        $screen->write("\e[1000Ca");

        $this->assertEquals("         a", $screen->output());
    }

    #[Test]
    public function doesnt_go_past_width_absolute()
    {
        $this->markTestSkipped('Need to implement wrapping');

        $screen = new Screen(30, 30);
        $screen->write("\e[1000Ga");

        // Manually verified that this is correct
        $this->assertEquals("                              \na", $screen->output());
    }


    #[Test]
    public function doesnt_go_past_height_relative()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[1000A12");

        $this->assertEquals(
            "1
2 12
3
4
5
6
7
8
9
10
11",
            $screen->output()
        );
    }

    #[Test]
    public function doesnt_go_past_height_home()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[H12");

        $this->assertEquals(
            "1
12
3
4
5
6
7
8
9
10
11",
            $screen->output()
        );
    }

    #[Test]
    public function clear_up_doesnt_go_off_screen()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[5A\e[1J");

        $this->assertEquals(
            "1





7
8
9
10
11",
            $screen->output()
        );
    }

    #[Test]
    public function clear_down()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[5A\e[0J");

        $this->assertEquals(
            "1
2
3
4
5
6




",
            $screen->output()
        );
    }

    #[Test]
    public function clear_doesnt_go_off_screen()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[2J");

        $this->assertEquals(
            "1









",
            $screen->output()
        );
    }


    #[Test]
    public function stash_restore_off_screen()
    {
        $screen = new Screen(180, 5);

        $screen->write(implode('', [
            "1\n",
            "2\n",
            "3\n",
            "4\n",
            "5\n",
            "6\n",
            "7\n",
            "8\n",
            "\e[2A\e7\e[2B",
            "9\n",
            "10\n",
            "11\e8a"
        ]));

        $this->assertEquals(
            "1
2
3
4
5
6
7
8
a
10
11",
            $screen->output()
        );
    }


    #[Test]
    public function manual_debug()
    {
         $this->markTestSkipped('Skipping manual debug');

        $captured = ob_get_clean();

        $term = new Terminal;
        $term->initDimensions();

        dump($term->cols());

        sleep(1);

        $commands = [
            "\e[2J", // clear screen
            "\e[1 q", // turn on block cursor
            "\e[10000F", // up lines, back to 0'

            "1\n",
            "2\n",
            "3\n",
            "4\n",
            "5\n",
            "6\n",
            "7\n",
            "8\n",
            "\e[2A\e7\e[2B",
            "9\n",
            "10\n",
            "11\e8a"
        ];


        foreach ($commands as $command) {
            echo $command;
            sleep(1);
        }

        sleep(4);

        ob_start();
        echo $captured;

//        $screen = new Screen(180, 10);
//
//        $screen->write(implode(PHP_EOL, [
//            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u',
//            'v', 'w', 'x', 'y', 'z'
//        ]));
//
//        $this->assertEquals("", $screen->output());

    }

}
