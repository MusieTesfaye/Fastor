<?php

namespace Fastor\Middleware;

use Fastor\Http\Request;

class CorsMiddleware
{
    private array $config;

    public function __construct(array $options = [])
    {
        $this->config = array_merge([
            'allow_origin' => '*',
            'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'allow_headers' => 'Content-Type, Authorization, X-Requested-With',
            'max_age' => 86400,
        ], $options);
    }

    public function __invoke(Request $request, callable $next)
    {
        // Handle Preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            $response = new \Fastor\Http\Response(new \OpenSwoole\Http\Response()); // Needs the raw Swoole response to inject headers
            // In Fastor's core loop, the Response is passed alongside the Request into the context.
            // For true middleware, it's better to modify the response after the handler returns, 
            // or return a special early response.
            $response = \response(); 
            
            $this->applyHeaders($response);
            $response->status(204);
            $response->end();
            return $response; // Return early
        }

        // Call next middleware/handler
        $result = $next($request);

        // Apply CORS headers to the outgoing response
        $response = \response();
        if ($response) {
            $this->applyHeaders($response);
        }

        return $result;
    }

    private function applyHeaders(\Fastor\Http\Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', $this->config['allow_origin']);
        $response->header('Access-Control-Allow-Methods', $this->config['allow_methods']);
        $response->header('Access-Control-Allow-Headers', $this->config['allow_headers']);
        $response->header('Access-Control-Max-Age', (string)$this->config['max_age']);
    }
}
