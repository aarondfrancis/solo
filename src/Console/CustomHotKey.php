<?php

namespace AaronFrancis\Solo\Console;

use AaronFrancis\Solo\Commands\Command;
use Closure;

class CustomHotKey
{
    /**
     * @param  string|array  $key
     * @param  string  $name
     * @param Closure(): void $callback
     * @param null|(Closure(Command): bool) $when
     */
    function __construct(
        public string|array $key,
        public string $name,
        private readonly Closure $callback,
        private readonly ?Closure $when = null,
    ) { }

    public function isActive(Command $command): bool
    {
        return $this->when?->call($this, $command) ?? true;
    }

    public function execute(): void
    {
        $this->callback->call($this);
    }
}