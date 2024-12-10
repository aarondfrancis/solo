<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Console\Commands;

use AaronFrancis\Solo\Facades\Solo as SoloAlias;
use App\Providers\AppServiceProvider;
use Laravel\SerializableClosure\Serializers\Signed;

class Test extends Solo
{
    protected $signature = 'solo:test {class} {provider}';

    protected $description = 'Run solo with an ad-hoc service provider';

    public function handle(): void
    {
        AppServiceProvider::allowCommandsFromTest($this->argument('class'));
        Signed::$signer = null;
        SoloAlias::clearCommands();

        $closure = unserialize($this->argument('provider'))->getClosure();

        call_user_func($closure);

        parent::handle();
    }
}
