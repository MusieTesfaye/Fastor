<?php

use Fastor\App;
use Fastor\Testing\TestCase;
use Fastor\Auth\JWT;
use Fastor\Auth\ApiKey;

// 1. Setup App with Auth routes
$app = App::getInstance();

// Simple JWT Protected Route
$app->get('/secure/jwt', function(JWT $auth, \Fastor\Http\Request $req) {
    $payload = $auth->handle($req);
    return ['user_id' => $payload['sub']];
});

// Simple ApiKey Protected Route
$app->get('/secure/key', function(ApiKey $auth, \Fastor\Http\Request $req) {
    $key = $auth->handle($req, 'secret-123');
    return ['authenticated' => true, 'key' => $key];
});

$app->get('/public', fn() => ['status' => 'ok']);

// 2. Run Tests via TestCase
class ProductionTest extends TestCase
{
    public function run()
    {
        echo "--- RUNNING PRODUCTION CORE TESTS ---\n\n";

        // Test 1: Public Route
        echo "Testing Public Route... ";
        $this->get('/public')
             ->assertStatus(200)
             ->assertJson(['status' => 'ok']);
        echo "PASSED\n";

        // Test 2: JWT Protected (Missing Token)
        echo "Testing JWT (Missing Token)... ";
        try {
            $this->get('/secure/jwt')
                 ->assertStatus(401);
            echo "PASSED\n";
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }

        // Test 3: JWT Protected (Success)
        echo "Testing JWT (Success)... ";
        $secret = "this_is_a_very_long_secret_key_32_chars_long!!";
        $jwt = new JWT($secret);
        $token = $jwt->encode(['sub' => 42]);
        putenv("FASTOR_SECRET=$secret");
        
        try {
            $this->get('/secure/jwt', ['Authorization' => "Bearer $token"])
                 ->assertStatus(200)
                 ->assertJson(['user_id' => 42]);
            echo "PASSED\n";
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }

        // Test 4: ApiKey Protected (Success)
        echo "Testing ApiKey (Success)... ";
        try {
            $this->get('/secure/key', ['X-API-Key' => 'secret-123'])
                 ->assertStatus(200)
                 ->assertJson(['authenticated' => true]);
            echo "PASSED\n";
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }

        // Test 5: ApiKey Protected (Failure)
        echo "Testing ApiKey (Failure)... ";
        try {
            $this->get('/secure/key', ['X-API-Key' => 'wrong'])
                 ->assertStatus(401);
            echo "PASSED\n";
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }

        echo "\n--- ALL TESTS COMPLETED ---\n";
    }
}

(new ProductionTest())->run();
