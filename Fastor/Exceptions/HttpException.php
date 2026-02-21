<?php

namespace Fastor\Exceptions;

class HttpException extends \Exception
{
    private mixed $detail;

    public function __construct(int $statusCode, mixed $detail = null, \Throwable $previous = null)
    {
        parent::__construct(is_string($detail) ? $detail : 'HTTP Exception', $statusCode, $previous);
        $this->detail = $detail;
    }

    public function getDetail(): mixed
    {
        return $this->detail;
    }
}
