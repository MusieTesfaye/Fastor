<?php

namespace Fastor\Middleware;

use Fastor\Http\Request;

class SecurityHeadersMiddleware
{
    private array $headers;

    public function __construct(array $headers = [])
    {
        $this->headers = array_merge([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ], $headers);
    }

    public function __invoke(Request $request, callable $next)
    {
        $result = $next($request);

        $response = \response();
        if ($response) {
            foreach ($this->headers as $key => $value) {
                $response->header($key, $value);
            }
        }

        return $result;
    }
}
