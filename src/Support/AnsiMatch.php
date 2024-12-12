<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Solo\Support;

readonly class AnsiMatch implements \Stringable
{
    public ?string $command;
    public ?string $params;

    public function __construct(public string $raw)
    {
        $pattern = <<<PATTERN
/
    (?<command_1>
        [ABCDEHIJKMNOSTZ=><12su78c]
    )
|
\\[
    (?<params_2>
        [0-9;?]*
    )
    (?<command_2>
        [@-~]
    )
/x
PATTERN;

        preg_match($pattern, $this->raw, $matches);

        $command = null;
        $params = null;

        foreach ($matches as $name => $value) {
            if (str_starts_with($name, 'command_')) {
                $command = $value;
            }

            if (str_starts_with($name, 'params_')) {
                $params = $value;
            }
        }

        $this->command = $command;
        $this->params = $params;
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}