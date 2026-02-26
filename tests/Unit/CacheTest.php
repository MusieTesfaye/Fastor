<?php

namespace Tests\Unit;

use Fastor\App;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class CacheTest
{
    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testDefaultCacheIsFileBased()
    {
        $app = App::getInstance();
        $cache = $app->getCache();

        // Use reflection to check the internal adapter of Psr16Cache
        $ref = new \ReflectionClass(Psr16Cache::class);
        $poolProp = $ref->getProperty('pool');
        $poolProp->setAccessible(true);
        $pool = $poolProp->getValue($cache);

        if (!($pool instanceof FilesystemAdapter)) {
            throw new \Exception("Default cache should be FilesystemAdapter, got " . get_class($pool));
        }
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function testExpressiveSyntax()
    {
        $app = App::getInstance();
        
        // Test array cache
        cache()->array();
        $pool = $this->getInternalPool($app->getCache());
        if (!($pool instanceof ArrayAdapter)) {
            throw new \Exception("Cache should be ArrayAdapter after cache()->array()");
        }

        // Test file cache
        cache()->file('test_cache_dir');
        $pool = $this->getInternalPool($app->getCache());
        if (!($pool instanceof FilesystemAdapter)) {
            throw new \Exception("Cache should be FilesystemAdapter after cache()->file()");
        }

        // Test redis cache
        if (extension_loaded('redis') || class_exists(\Predis\Client::class)) {
            cache()->redis('redis://127.0.0.1:6379');
            $pool = $this->getInternalPool($app->getCache());
            if (!($pool instanceof RedisAdapter)) {
                throw new \Exception("Cache should be RedisAdapter after cache()->redis()");
            }
        } else {
            echo "(Skipping Redis test: extension/package missing) ";
        }

        // Test memcached cache
        if (extension_loaded('memcached')) {
            cache()->memcached('memcached://127.0.0.1:11211');
            $pool = $this->getInternalPool($app->getCache());
            // Need to check its internal pool if it's a specific adapter
        }
    }

    private function getInternalPool($cache)
    {
        $ref = new \ReflectionClass(Psr16Cache::class);
        $poolProp = $ref->getProperty('pool');
        $poolProp->setAccessible(true);
        return $poolProp->getValue($cache);
    }

    public function run()
    {
        // echo "Running CacheTest... ";
        $this->testDefaultCacheIsFileBased();
        $this->testExpressiveSyntax();
        echo "PASSED\n";
    }
}
