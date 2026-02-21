<?php

namespace Fastor\WebSocket;

use OpenSwoole\WebSocket\Server;

class Broadcast
{
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Send a message to all connected clients.
     */
    public function all(mixed $data): void
    {
        $payload = is_string($data) ? $data : json_encode($data);
        foreach ($this->server->connections as $fd) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $payload);
            }
        }
    }

    /**
     * Send a message to specific client IDs (FDs).
     */
    public function to(array $fds, mixed $data): void
    {
        $payload = is_string($data) ? $data : json_encode($data);
        foreach ($fds as $fd) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $payload);
            }
        }
    }

    /**
     * Send a message to everyone except the specified FDs.
     */
    public function except(array $excludeFds, mixed $data): void
    {
        $payload = is_string($data) ? $data : json_encode($data);
        foreach ($this->server->connections as $fd) {
            if (!in_array($fd, $excludeFds) && $this->server->isEstablished($fd)) {
                $this->server->push($fd, $payload);
            }
        }
    }
}
