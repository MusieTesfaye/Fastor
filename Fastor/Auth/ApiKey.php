<?php

namespace Fastor\Auth;

use Fastor\Http\Request;

class ApiKey
{
    private string $name;
    private string $in; // 'header' or 'query'

    public function __construct(string $name = 'X-API-Key', string $in = 'header')
    {
        $this->name = $name;
        $this->in = $in;
    }

    /**
     * Validate the key against a known value or callback.
     */
    public function handle(Request $request, string|callable $validator): string
    {
        $key = ($this->in === 'header') 
            ? $request->header($this->name) 
            : $request->query($this->name);

        if (!$key) {
            throw new \Fastor\Exceptions\HttpException(401, "Missing API Key ({$this->name} in {$this->in})");
        }

        if (is_callable($validator)) {
            if (!$validator($key)) {
                throw new \Fastor\Exceptions\HttpException(401, "Invalid API Key");
            }
        } elseif ($key !== $validator) {
            throw new \Fastor\Exceptions\HttpException(401, "Invalid API Key");
        }

        return $key;
    }
}
