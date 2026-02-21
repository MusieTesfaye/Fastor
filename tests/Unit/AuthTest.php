<?php

namespace Tests\Unit;

use Fastor\Auth\JWT;
use Fastor\Auth\ApiKey;
use Fastor\Http\Request;

class AuthTest
{
    private function createMockRequest(array $query = [], array $headers = [])
    {
        $swooleMock = new class($query, $headers) {
            public $get;
            public $header;
            public $server = ['request_method' => 'GET', 'request_uri' => '/'];
            public function __construct($get, $header) {
                $this->get = $get;
                $this->header = $header;
            }
            public function rawContent() { return ''; }
        };
        return new Request($swooleMock);
    }

    public function testJwtFlow()
    {
        $secret = "super_secret_key_that_is_long_enough_32kb";
        $jwt = new JWT($secret);
        
        $payload = ['sub' => 123, 'name' => 'John'];
        $token = $jwt->encode($payload);
        
        $decoded = $jwt->decode($token);
        if ($decoded['sub'] !== 123) {
            throw new \Exception("JWT Decode failed: sub mismatch");
        }

        $request = $this->createMockRequest([], ['authorization' => "Bearer $token"]);
        $received = $jwt->handle($request);
        if ($received['sub'] !== 123) {
            throw new \Exception("JWT Handle failed to extract from request");
        }
    }

    public function testApiKeyFlow()
    {
        $apiKey = new ApiKey('X-API-Key', 'header');
        $request = $this->createMockRequest([], ['x-api-key' => 'secret-123']);
        
        $val = $apiKey->handle($request, 'secret-123');
        if ($val !== 'secret-123') {
            throw new \Exception("ApiKey Handle failed validation");
        }

        // Test callback
        $val = $apiKey->handle($request, fn($k) => $k === 'secret-123');
        if ($val !== 'secret-123') {
            throw new \Exception("ApiKey Handle failed callback validation");
        }
    }

    public function run()
    {
        echo "Running AuthTest... ";
        $this->testJwtFlow();
        $this->testApiKeyFlow();
        echo "PASSED\n";
    }
}
