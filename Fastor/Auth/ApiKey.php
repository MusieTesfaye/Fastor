<?php

namespace Fastor\Auth;

use Fastor\Http\Request;
use Fastor\Exceptions\HttpException;

class ApiKey
{
    public function __construct(
        private string $name = 'api_key',
        private string $in = 'header' // 'header' or 'query'
    ) {}

    /**
     * Extracts an API key from header or query.
     */
    public function __invoke(Request $request): string
    {
        $val = $this->in === 'header' 
            ? $request->header($this->name) 
            : $request->query($this->name);

        if (!$val) {
            throw new HttpException(401, "Missing API Key ({$this->name} in {$this->in})");
        }

        return $val;
    }
}
