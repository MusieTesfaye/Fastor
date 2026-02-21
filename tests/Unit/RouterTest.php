<?php

namespace Tests\Unit;

use Fastor\Router;

class RouterTest
{
    public function testRouteRegistration()
    {
        $router = new Router();
        $router->get('/test', fn() => 'hello');
        
        $routes = $router->getRoutes();
        if (count($routes) !== 1) {
            throw new \Exception("Expected 1 route, got " . count($routes));
        }
        
        if ($routes[0]['path'] !== '/test' || $routes[0]['method'] !== 'GET') {
            throw new \Exception("Route registration failed: " . json_encode($routes[0]));
        }
    }

    public function testRoutePreMatch()
    {
        $router = new Router();
        $router->get('/secure', fn() => 'secret', ['public' => false]);
        $router->get('/public', fn() => 'hello')->public();
        
        $secureOptions = $router->preMatch('GET', '/secure');
        if (($secureOptions['public'] ?? true) !== false) {
            throw new \Exception("Pre-match failed for secure route");
        }
        
        $publicOptions = $router->preMatch('GET', '/public');
        if (($publicOptions['public'] ?? false) !== true) {
            throw new \Exception("Pre-match failed for public route");
        }
    }

    public function testIncludeRouter()
    {
        $parent = new Router();
        $child = new Router();
        
        $child->get('/hello', fn() => 'hi');
        $parent->includeRouter($child, '/api/v1');
        
        $routes = $parent->getRoutes();
        if ($routes[0]['path'] !== '/api/v1/hello') {
            throw new \Exception("Include router failed path mapping: " . $routes[0]['path']);
        }
    }

    public function run()
    {
        echo "Running RouterTest... ";
        $this->testRouteRegistration();
        $this->testRoutePreMatch();
        $this->testIncludeRouter();
        echo "PASSED\n";
    }
}
