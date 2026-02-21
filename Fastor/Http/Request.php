<?php

namespace Fastor\Http;

use OpenSwoole\Http\Request as SwooleRequest;

class Request
{
    private $swooleRequest;
    private ?array $json = null;
    public array $attributes = [];
    private ?string $virtualRawContent = null;

    public function __construct($swooleRequest)
    {
        $this->swooleRequest = $swooleRequest;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function setAttribute(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->swooleRequest->get[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->swooleRequest->post ?? [];
        return $this->swooleRequest->post[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        return $this->swooleRequest->header[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->swooleRequest->header;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->swooleRequest->cookie[$key] ?? $default;
    }

    public function cookies(): array
    {
        return $this->swooleRequest->cookie ?? [];
    }

    public function json(): array
    {
        if ($this->json === null) {
            $raw = $this->rawContent();
            if (empty($raw)) {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Fastor\Exceptions\HttpException(400, "Invalid JSON payload: " . json_last_error_msg());
            }
            $this->json = $decoded ?: [];
        }
        return $this->json;
    }

    public function method(): string
    {
        return $this->swooleRequest->server['request_method'];
    }

    public function uri(): string
    {
        return $this->swooleRequest->server['request_uri'];
    }

    public function rawContent(): string
    {
        return $this->virtualRawContent ?? $this->swooleRequest->rawContent() ?: '';
    }

    public function setVirtualRawContent(string $content): self
    {
        $this->virtualRawContent = $content;
        return $this;
    }

    public function swoole()
    {
        return $this->swooleRequest;
    }

    public function __get(string $name)
    {
        if ($name === 'get') return $this->swooleRequest->get;
        if ($name === 'post') return $this->swooleRequest->post;
        if ($name === 'header') return $this->swooleRequest->header;
        return null;
    }
}
