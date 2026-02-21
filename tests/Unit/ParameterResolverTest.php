<?php

namespace Tests\Unit;

use Fastor\ParameterResolver;
use Fastor\Validation\Mapper;
use Fastor\Http\Request;

class ParameterResolverTest
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

    public function testResolvePrimitivesFromQuery()
    {
        $resolver = new ParameterResolver(new Mapper());
        $request = $this->createMockRequest(['id' => '123', 'name' => 'fastor']);
        
        // Mocking parameter structure that ReflectionCache would return
        $paramId = ['name' => 'id', 'type' => 'int', 'attributes' => [], 'has_default' => false];
        $paramName = ['name' => 'name', 'type' => 'string', 'attributes' => [], 'has_default' => false];
        
        $method = new \ReflectionMethod($resolver, 'resolveParameter');
        $method->setAccessible(true);
        
        $resolvedId = $method->invoke($resolver, $paramId, [], $request);
        $resolvedName = $method->invoke($resolver, $paramName, [], $request);
        
        if ($resolvedId !== 123) {
            throw new \Exception("Failed to resolve and cast 'int' parameter");
        }
        if ($resolvedName !== 'fastor') {
            throw new \Exception("Failed to resolve 'string' parameter");
        }
    }

    public function testResolveFromAttributes()
    {
        $resolver = new ParameterResolver(new Mapper());
        $request = $this->createMockRequest([], ['x-api-key' => 'secret']);
        
        $attrHeader = new \Fastor\Attributes\Header('x-api-key');
        $param = ['name' => 'key', 'type' => 'string', 'attributes' => [$attrHeader], 'has_default' => false];
        
        $method = new \ReflectionMethod($resolver, 'resolveParameter');
        $method->setAccessible(true);
        
        $resolved = $method->invoke($resolver, $param, [], $request);
        
        if ($resolved !== 'secret') {
            throw new \Exception("Failed to resolve parameter from #[Header] attribute");
        }
    }

    public function run()
    {
        echo "Running ParameterResolverTest... ";
        $this->testResolvePrimitivesFromQuery();
        $this->testResolveFromAttributes();
        echo "PASSED\n";
    }
}
