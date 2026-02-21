<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "--- FASTOR COMPREHENSIVE TEST SUITE ---\n\n";

$tests = [
    // Unit Tests
    \Tests\Unit\RouterTest::class,
    \Tests\Unit\MapperTest::class,
    \Tests\Unit\ParameterResolverTest::class,
    \Tests\Unit\AuthTest::class,
    
    // Integration Tests
    \Tests\Integration\AppFlowTest::class,
    \Tests\Integration\DatabaseIntegrationTest::class,
    \Tests\Integration\OpenApiDocTest::class,
];

// Ensure we load the test files manually since our autoloader might be configured for Fastor/ only
foreach (glob(__DIR__ . '/Unit/*.php') as $filename) require_once $filename;
foreach (glob(__DIR__ . '/Integration/*.php') as $filename) require_once $filename;

$failCount = 0;
foreach ($tests as $testClass) {
    try {
        echo "Running " . (new \ReflectionClass($testClass))->getShortName() . "... ";
        \Fastor\App::resetInstance();
        (new $testClass())->run();
    } catch (\Throwable $e) {
        echo "FAILED: " . get_class($e) . ": " . $e->getMessage() . "\n";
        // echo $e->getTraceAsString() . "\n";
        $failCount++;
    }
}

echo "\n--- TEST RESULTS ---\n";
if ($failCount === 0) {
    echo "ALL TESTS PASSED! 🚀\n";
    exit(0);
} else {
    echo "$failCount TESTS FAILED. ❌\n";
    exit(1);
}
