<?php
/** @noinspection PhpRedundantCatchClauseInspection */

namespace Fastor\Cache;

use Fastor\App;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class CacheConfigurator
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Set a custom PSR-6 adapter.
     */
    public function custom(AdapterInterface $adapter): App
    {
        return $this->app->useCache($adapter);
    }

    /**
     * Use file-based cache.
     */
    public function file(string $directory = 'cache', int $defaultLifetime = 0): App
    {
        return $this->custom(new FilesystemAdapter('', $defaultLifetime, $directory));
    }

    /**
     * Use Redis cache.
     */
    public function redis(string $dsn = 'redis://localhost', array $options = []): App
    {
        $client = RedisAdapter::createConnection($dsn, $options);
        return $this->custom(new RedisAdapter($client));
    }

    /**
     * Use Memcached cache.
     */
    public function memcached(string $dsn = 'memcached://localhost'): App
    {
        $client = MemcachedAdapter::createConnection($dsn);
        return $this->custom(new MemcachedAdapter($client));
    }

    /**
     * Use APCu cache.
     */
    public function apcu(string $namespace = '', int $defaultLifetime = 0): App
    {
        return $this->custom(new ApcuAdapter($namespace, $defaultLifetime));
    }

    /**
     * Use in-memory (array) cache.
     */
    public function array(int $defaultLifetime = 0): App
    {
        return $this->custom(new ArrayAdapter($defaultLifetime));
    }
}
