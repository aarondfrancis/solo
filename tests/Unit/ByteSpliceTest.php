<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Tests\Unit;

use AaronFrancis\Solo\Support\SafeBytes;
use PHPUnit\Framework\Attributes\Test;

class ByteSpliceTest extends Base
{
    #[Test]
    public function empty(): void
    {
        $string = '';

        $this->assertEquals(['', ''], SafeBytes::parse($string));
    }

    #[Test]
    public function under10(): void
    {
        $string = 'ab';

        $this->assertEquals([$string, ''], SafeBytes::parse($string));
    }

    #[Test]
    public function under10_spliced(): void
    {
        $spliced = "\xF0";
        $string = 'ab' . $spliced;

        $this->assertEquals(['ab', $spliced], SafeBytes::parse($string));
    }

    #[Test]
    public function simple_one_byte(): void
    {
        $string = 'a-b-c-d-e-f-g-h-i-j-k-l-m-n-o-p-q-r-s-t-u-v-w-x-y-z';

        $this->assertEquals([$string, ''], SafeBytes::parse($string));
    }

    #[Test]
    public function simple_multibyte(): void
    {
        $string = 'a─b─c─d─e─f─g─h─i─j─k─l─m─n─o─p─q─r─s─t─u─v';

        $this->assertEquals([$string, ''], SafeBytes::parse($string));
    }

    #[Test]
    public function only_spliced(): void
    {
        // First byte of a 2-byte character
        $spliced = "\xF0";

        $this->assertEquals(['', $spliced], SafeBytes::parse($spliced));
    }

    #[Test]
    public function spliced_four(): void
    {
        // First byte of a 2-byte character
        $spliced = "\xF0";
        $string = "─────────────────────$spliced";

        $this->assertEquals(['─────────────────────', $spliced], SafeBytes::parse($string));
    }

    #[Test]
    public function spliced_three(): void
    {
        // First byte of a 3-byte character
        $spliced = "\xE2";
        $string = "─────────────────────$spliced";

        $this->assertEquals(['─────────────────────', $spliced], SafeBytes::parse($string));
    }

    #[Test]
    public function spliced_two(): void
    {
        // First byte of a 2-byte character
        $spliced = "\xC2";
        $string = "─────────────────────$spliced";

        $this->assertEquals(['─────────────────────', $spliced], SafeBytes::parse($string));
    }
}
