<?php

namespace Fastor\WebSocket;

use OpenSwoole\WebSocket\Frame as SwooleFrame;

class Frame
{
    private SwooleFrame $swooleFrame;

    public function __construct(SwooleFrame $swooleFrame)
    {
        $this->swooleFrame = $swooleFrame;
    }

    public function data(): mixed
    {
        return $this->swooleFrame->data;
    }

    public function fd(): int
    {
        return $this->swooleFrame->fd;
    }

    public function opcode(): int
    {
        return $this->swooleFrame->opcode;
    }

    public function finish(): bool
    {
        return $this->swooleFrame->finish;
    }

    public function swoole(): SwooleFrame
    {
        return $this->swooleFrame;
    }
}
