<?php

namespace Fastor\Testing;

use Fastor\Http\Response;

class TestResponse
{
    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function assertStatus(int $code): self
    {
        $actual = $this->response->swoole()->status;
        if ($actual !== $code) {
            throw new \Exception("Expected status $code, got $actual");
        }
        return $this;
    }

    public function assertJson(array $data): self
    {
        $content = $this->response->swoole()->content;
        $actual = json_decode($content, true);
        
        if ($actual === null) {
            throw new \Exception("Response is not valid JSON: $content");
        }

        foreach ($data as $key => $value) {
            if (!isset($actual[$key]) || $actual[$key] !== $value) {
                throw new \Exception("JSON mismatch at key '$key'. Expected " . json_encode($value) . ", got " . json_encode($actual[$key] ?? null));
            }
        }

        return $this;
    }

    public function assertContent(string $content): self
    {
        $actual = $this->response->swoole()->content;
        if ($actual !== $content) {
            throw new \Exception("Expected content '$content', got '$actual'");
        }
        return $this;
    }

    public function getContent(): string
    {
        return $this->response->swoole()->content;
    }

    public function json(): array
    {
        return json_decode($this->getContent(), true) ?: [];
    }
}
