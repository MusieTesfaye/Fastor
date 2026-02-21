<?php

namespace Fastor;

use OpenSwoole\Http\Server;

class BackgroundTasks
{
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Add a task to be executed in the background.
     * Note: The task must be a closure and will be serialized.
     */
    public function add(callable $task): void
    {
        // Swoole's task() expects a value that can be serialized.
        // We wrap the closure in a way that the worker can execute it.
        $this->server->task($task);
    }
}
