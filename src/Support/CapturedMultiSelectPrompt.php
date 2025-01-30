<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\Key;
use Laravel\Prompts\MultiSelectPrompt;

class CapturedMultiSelectPrompt extends MultiSelectPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;

    public function handleInput($key)
    {
        $continue = $this->callNativeKeyPressHandler($key);

        if ($continue === false || $key === Key::CTRL_C) {
            $this->complete = true;
            $this->clearListeners();

            if ($key === Key::CTRL_C) {
                // @TODO Cancel
            }

            if ($key === Key::CTRL_U) {
                // @TODO Revert
            }
        }
    }
}
