<?php

namespace Fastor;

use Fastor\Http\Request;
use Fastor\WebSocket\Frame;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use OpenSwoole\Http\Server as SwooleServer;
use function FastRoute\simpleDispatcher;
use Fastor\Http\Response;

class Router
{
    private array $routes = [];
    private array $wsRoutes = [];
    private ?Dispatcher $dispatcher = null;
    private ?Dispatcher $wsDispatcher = null;
    private array $routeMiddleware = [];

    public function addRoute(string $method, string $path, callable|string|array $handler, array $options = []): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'options' => $options
        ];
        $this->dispatcher = null;
        return $this;
    }

    public function get(string $path, callable|string|array $handler, array $options = []): self
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    public function post(string $path, callable|string|array $handler, array $options = []): self
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    public function put(string $path, callable|string|array $handler, array $options = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    public function patch(string $path, callable|string|array $handler, array $options = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    public function delete(string $path, callable|string|array $handler, array $options = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    public function addWsRoute(string $path, callable $handler): self
    {
        $this->wsRoutes[] = [$path, $handler];
        $this->wsDispatcher = null;
        return $this;
    }

    public function includeRouter(Router $router, string $prefix = ''): self
    {
        $prefix = rtrim($prefix, '/');
        foreach ($router->routes as $index => $route) {
            $newPath = $prefix . '/' . ltrim($route['path'], '/');
            $newIndex = count($this->routes);
            $this->routes[] = [
                'method' => $route['method'],
                'path' => $newPath,
                'handler' => $route['handler'],
                'options' => $route['options']
            ];
            
            if (isset($router->routeMiddleware[$index])) {
                $this->routeMiddleware[$newIndex] = $router->routeMiddleware[$index];
            }
        }

        foreach ($router->wsRoutes as $route) {
            $newPath = $prefix . '/' . ltrim($route[0], '/');
            $this->wsRoutes[] = [$newPath, $route[1]];
        }

        $this->dispatcher = null;
        $this->wsDispatcher = null;
        return $this;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function middleware(callable $middleware): self
    {
        // Apply middleware to the last added route
        $lastRouteIndex = count($this->routes) - 1;
        if ($lastRouteIndex >= 0) {
            $this->routeMiddleware[$lastRouteIndex][] = $middleware;
        }
        return $this;
    }

    public function public(): self
    {
        $lastRouteIndex = count($this->routes) - 1;
        if ($lastRouteIndex >= 0) {
            $this->routes[$lastRouteIndex]['options']['public'] = true;
        }
        return $this;
    }

    /**
     * Pre-match a route to get its options BEFORE the middleware stack runs.
     * This is critical so middleware can read options like 'public' to decide 
     * whether to apply auth checks etc.
     */
    public function preMatch(string $method, string $uri): array
    {
        $this->buildDispatcher();
        $routeInfo = $this->dispatcher->dispatch($method, $uri);
        if ($routeInfo[0] === \FastRoute\Dispatcher::FOUND) {
            $index = $routeInfo[1]['index'];
            return $this->routes[$index]['options'] ?? [];
        }
        return [];
    }

    private function buildDispatcher(): void
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
                foreach ($this->routes as $index => $route) {
                    $r->addRoute($route['method'], $route['path'], ['handler' => $route['handler'], 'index' => $index]);
                }
            });
        }
    }

    public function dispatch(string $method, string $uri, Request $request, Response $response, ParameterResolver $resolver, ?SwooleServer $server = null): mixed
    {
        $this->buildDispatcher();

        $routeInfo = $this->dispatcher->dispatch($method, $uri);

        if ($routeInfo[0] !== \FastRoute\Dispatcher::FOUND) {
             // Dispatcher handles the 404/405 below
        }

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                throw new \Fastor\Exceptions\HttpException(404, 'Not Found');
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                throw new \Fastor\Exceptions\HttpException(405, 'Method Not Allowed');
            case \FastRoute\Dispatcher::FOUND:
                $handlerInfo = $routeInfo[1];
                $vars = $routeInfo[2];
                $index = $handlerInfo['index'];
                $options = $this->routes[$index]['options'] ?? [];

                // Attach options to request for middleware access
                $request->setAttribute('route_options', $options);
                
                $coreHandler = function ($request) use ($handlerInfo, $vars, $response, $resolver, $server) {
                    return $this->resolveAndCall($handlerInfo['handler'], $vars, $request, $response, $resolver, $server);
                };

                if (isset($this->routeMiddleware[$index])) {
                    $stack = new \Fastor\Middleware\MiddlewareStack();
                    foreach ($this->routeMiddleware[$index] as $mw) {
                        $stack->add($mw);
                    }
                    $data = $stack->handle($request, $coreHandler);
                } else {
                    $data = $coreHandler($request);
                }

                return ['data' => $data, 'options' => $options];
        }

        return null;
    }

    public function dispatchWs(string $uri, Request|Frame $request, ParameterResolver $resolver, ?SwooleServer $server = null): mixed
    {
        if ($this->wsDispatcher === null) {
            $this->wsDispatcher = simpleDispatcher(function (RouteCollector $r) {
                foreach ($this->wsRoutes as $index => $route) {
                    $r->addRoute('GET', $route[0], ['handler' => $route[1], 'index' => $index]);
                }
            });
        }

        $routeInfo = $this->wsDispatcher->dispatch('GET', $uri);

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $handlerInfo = $routeInfo[1];
            $vars = $routeInfo[2];
            
            try {
                $args = $resolver->resolve($handlerInfo['handler'], $vars, $request);
                return call_user_func_array($handlerInfo['handler'], $args);
            } catch (\Throwable $e) {
                // Ignore errors on Open if it's just a parameter mismatch
                if ($request instanceof Request) {
                    return null;
                }
                throw $e;
            }
        }

        return null;
    }

    private function resolveAndCall($handler, array $pathVars, $request, Response $response, ParameterResolver $resolver, ?SwooleServer $server = null): mixed
    {
        $resolver->setResponse($response);
        $args = $resolver->resolve($handler, $pathVars, $request);

        if (is_callable($handler)) {
            return call_user_func_array($handler, $args);
        }

        // Handle class string handlers (e.g. Controller@method)
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $instance = new $class();
            return call_user_func_array([$instance, $method], $args);
        }

        throw new \Exception('Invalid handler', 500);
    }
}
