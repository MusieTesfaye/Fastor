<?php

namespace Fastor\Http;

class Response
{
    private $swooleResponse;

    public function __construct($swooleResponse)
    {
        $this->swooleResponse = $swooleResponse;
    }

    public function status(int $code): self
    {
        $this->swooleResponse->status($code);
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->swooleResponse->header($key, $value);
        return $this;
    }

    public function json(mixed $data): self
    {
        $this->header('Content-Type', 'application/json');
        $this->swooleResponse->end(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $this;
    }

    public function html(string $html): self
    {
        $this->header('Content-Type', 'text/html');
        $this->swooleResponse->end($html);
        return $this;
    }

    public function end(string $content = ''): void
    {
        if (method_exists($this->swooleResponse, 'end')) {
            $this->swooleResponse->end($content);
        }
    }

    public function redirect(string $url, int $code = 302): void
    {
        $this->status($code);
        $this->header('Location', $url);
        $this->end();
    }

    public function cookie(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $samesite = ''
    ): self {
        $this->swooleResponse->cookie($name, $value, $expire, $path, $domain, $secure, $httponly, $samesite);
        return $this;
    }

    public function file(string $path): void
    {
        if (!file_exists($path)) {
            $this->status(404)->end('File not found');
            return;
        }
        $this->swooleResponse->sendfile($path);
    }

    public function swoole()
    {
        return $this->swooleResponse;
    }
}
