<?php

namespace Fastor\Testing;

use Fastor\App;
use Fastor\Http\Request;
use OpenSwoole\Http\Request as SwooleRequest;

class TestCase
{
    // No longer caching App instance to avoid stale singleton issues in tests
    
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    protected function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        // Mock a Swoole Request
        $swooleRequest = new SwooleRequest();
        $swooleRequest->server = [
            'request_method' => strtoupper($method),
            'request_uri' => $uri,
            'remote_addr' => '127.0.0.1'
        ];
        $swooleRequest->header = array_change_key_case($headers, CASE_LOWER);
        
        if (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT') {
            $swooleRequest->post = $data;
        } else {
            $swooleRequest->get = $data;
        }

        $request = new Request($swooleRequest);
        
        // Manual override for JSON data in virtual requests
        if (!empty($data) && (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json')) {
            $request->setVirtualRawContent(json_encode($data));
        }

        $app = App::getInstance();
        $response = $app->handleVirtualRequest($request);
        
        return new TestResponse($response);
    }
}
