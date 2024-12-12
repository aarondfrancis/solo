<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Tests\Unit;

use AaronFrancis\Solo\Providers\SoloServiceProvider;
use AaronFrancis\Solo\Tests\Support\SoloTestServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class Base extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetup($app) {}

    protected function getPackageProviders($app)
    {
        return [
            SoloServiceProvider::class,
            SoloTestServiceProvider::class,
        ];
    }
}
