<?php

namespace Tests\Integration;

use Fastor\App;
use Fastor\Testing\TestCase;

class OpenApiDocTest extends TestCase
{
    public function testOpenApiJsonGeneration()
    {
        \Fastor\App::resetInstance();
        $app = App::getInstance();
        $app->get('/api/test', fn() => 'ok');

        $response = $this->get('/openapi.json');
        $response->assertStatus(200);
        
        $content = $response->response()->swoole()->content;
        $json = json_decode($content, true);
        
        if ($json === null) {
            throw new \Exception("OpenAPI response is not valid JSON: " . $content);
        }

        if (!isset($json['paths']['/api/test'])) {
            \Fastor\Logging\Logger::info("JSON Paths: " . json_encode(array_keys($json['paths'] ?? [])));
            throw new \Exception("OpenAPI JSON is missing /api/test. Found paths: " . implode(', ', array_keys($json['paths'] ?? [])));
        }
    }

    public function run()
    {
        echo "Running OpenApiDocTest... ";
        $this->testOpenApiJsonGeneration();
        echo "PASSED\n";
    }
}
