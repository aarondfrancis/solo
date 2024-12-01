<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Tests\Integration;

use AaronFrancis\Solo\Helpers\AnsiAware;
use AaronFrancis\Solo\Providers\SoloServiceProvider;
use AaronFrancis\Solo\Support\PendingProcess;
use AaronFrancis\Solo\Tests\Support\SoloTestServiceProvider;
use Closure;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\ProcessResult;
use Laravel\Prompts\Key;
use Laravel\Prompts\Terminal;
use Laravel\SerializableClosure\SerializableClosure;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Process\InputStream;

abstract class Base extends TestCase
{
    protected InvokedProcess $process;

    protected InputStream $input;

    protected string $frame = '';

    protected string $previousFrame = '';

    protected bool $newBuffer = false;

    protected int $width;

    protected int $height;

    protected int $reservedLines = 4;

    protected function getPackageProviders($app)
    {
        return [
            SoloServiceProvider::class,
            SoloTestServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        $this->input = new InputStream;

        $terminal = new Terminal;
        $terminal->initDimensions();

        $this->width = $terminal->cols();
        $this->height = $terminal->lines() - $this->reservedLines;

        parent::setUp();
    }

    protected function runSolo(array $actions, ?Closure $provider = null)
    {
        // PHPUnit captures output, so we need to put an
        // end to that so we can see Solo on screen.
        $stash = ob_get_clean();

        // Pass a closure to the solo:test command so that we can
        // configure Solo in different ways for the tests.
        $closure = new SerializableClosure($provider ?? function () {
            //
        });

        $this->process = $this->startProcess($closure);

        // Hide the cursor and start an alt screen
        $this->write("\e[?25l" . "\e[?1049h");

        $result = $this->loop($actions);

        if ($result->exitCode() !== 0) {
            // Move up, clear down, and then print the
            // errors from the underlying process.
            $this->write("\e[1000F" . "\e[0J");
            $this->write($this->frame);
        }

        // Kill alt screen
        $this->write("\e[?25h" . "\e[?1049l");

        // Turn on PHPUnit's buffering again.
        ob_start();

        // And put whatever we stashed back into the buffer.
        echo $stash;
    }

    public function withSnapshot(Closure $callback)
    {
        return function () use ($callback) {
            $callback($this->previousFrame, AnsiAware::plain($this->previousFrame));
        };
    }

    protected function startProcess(SerializableClosure $closure): InvokedProcess
    {
        return app(PendingProcess::class)
            ->command([
                'php', 'vendor/bin/testbench', 'solo:test',
                static::class,
                serialize($closure)
            ])
            ->input($this->input)
            ->pty()
            ->forever()
            ->env([
                // Disable the underlying alt screen of Solo
                'NO_ALT_SCREEN' => '1',
                'FORCE_COLOR' => '1',
                'COLUMNS' => $this->width,
                'LINES' => $this->height,
            ])
            ->start(null, function ($type, $buffer) {
                $this->newBuffer = true;

                // Move to top means that Solo is starting a new frame.
                $move = "\e[{$this->height}F";

                if (str_contains($buffer, $move)) {
                    $this->previousFrame = $this->frame;
                    // Move all the way up, but then down four lines.
                    $this->frame = "\e[1000F\e[{$this->reservedLines}B" . last(explode($move, $buffer));
                } else {
                    $this->frame .= $buffer;
                }
            });
    }

    protected function write(string $string)
    {
        echo $string;
    }

    protected function loop(array $actions): ProcessResult
    {
        $millisecondsSinceLastAction = 0;
        $millisecondsBetweenFrames = 10;
        $millisecondsBetweenActions = 500;

        while ($this->process->running()) {
            // Move up 1000 rows to column 1
            $this->write("\e[1000F");
            // Down a few lines
            $this->write("\e[" . ($this->reservedLines - 1) . "B");
            // Clear to beginning
            $this->write("\e[1J");
            // Move back up
            $this->write("\e[" . ($this->reservedLines - 1) . "A");

            // @TODO more status?
            $this->write('Running tests...' . " ($millisecondsSinceLastAction)");
            $this->write("\n\n\n\n");

            if ($this->newBuffer) {
                $this->newBuffer = false;
                $this->write($this->frame);
            }

            usleep($millisecondsBetweenFrames * 1000);
            $millisecondsSinceLastAction += $millisecondsBetweenFrames;

            if ($millisecondsSinceLastAction < $millisecondsBetweenActions) {
                continue;
            }

            $millisecondsSinceLastAction = 0;

            if (count($actions)) {
                $action = array_shift($actions);
            } else {
                $action = Key::CTRL_C;
            }

            if (is_string($action)) {
                // All strings get written to the underlying process.
                $this->input->write($action);
            } elseif (is_int($action)) {
                // If it's an integer, just back up that many
                // milliseconds to delay the next action.
                $millisecondsSinceLastAction = -1 * $action;
            } else {
                // Any functions just get called.
                call_user_func($action);
            }
        }

        return $this->process->wait();
    }
}