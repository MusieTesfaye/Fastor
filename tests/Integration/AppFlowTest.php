<?php

namespace Tests\Integration;

use Fastor\App;
use Fastor\Testing\TestCase;
use Fastor\Http\Response;

class AppFlowTest extends TestCase
{
    public function testFullRequestFlow()
    {
        App::resetInstance();
        $this->app = App::getInstance();
        $this->app->get('/hello', fn() => ['message' => 'world']);
        
        $this->get('/hello')
             ->assertStatus(200)
             ->assertJson(['message' => 'world']);
    }

    public function testMiddlewareFlow()
    {
        App::resetInstance();
        $this->app = App::getInstance();
        $this->app->addMiddleware(function($req, $next) {
            $response = $next($req);
            $response->header('X-Middleware', 'applied');
            return $response;
        });

        $this->app->get('/mw', function(Response $res) {
            return $res->json(['status' => 'ok']);
        });

        $response = $this->get('/mw');
        $response->assertStatus(200);
        
        $headers = $response->response()->swoole()->headers;
        // Case-insensitive header check
        $middlewareHeader = null;
        foreach ($headers as $key => $val) {
            if (strtolower($key) === 'x-middleware') {
                $middlewareHeader = $val;
                break;
            }
        }

        if (strtolower($middlewareHeader ?? '') !== 'applied') {
            throw new \Exception("Middleware was not executed correctly. Found headers: " . json_encode($headers));
        }
    }

    public function test404()
    {
        App::resetInstance();
        $this->get('/non-existent')
             ->assertStatus(404);
    }

    public function run()
    {
        echo "Running AppFlowTest... ";
        $this->testFullRequestFlow();
        $this->testMiddlewareFlow();
        $this->test404();
        echo "PASSED\n";
    }
}
