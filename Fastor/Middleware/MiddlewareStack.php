<?php

namespace Fastor\Middleware;

use Fastor\Http\Request;
use OpenSwoole\Http\Response;

class MiddlewareStack
{
    private array $middlewares = [];

    public function add(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function handle(Request $request, callable $coreHandler): mixed
    {
        $stack = array_reverse($this->middlewares);
        
        $next = $coreHandler;

        foreach ($stack as $middleware) {
            $next = function (Request $req) use ($middleware, $next) {
                return $middleware($req, $next);
            };
        }

        return $next($request);
    }
}
