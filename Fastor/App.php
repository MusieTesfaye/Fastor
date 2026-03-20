<?php

namespace Fastor;

use OpenSwoole\WebSocket\Server as SwooleServer;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use FastRoute\Dispatcher;
use Fastor\Http\Request as FastorRequest;
use Fastor\Http\Response as FastorResponse;
use Fastor\Validation\Mapper;
use Fastor\Exceptions\HttpException;
use Fastor\Logging\Logger;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Psr16Cache;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

class App
{
    private static ?App $instance = null;
    private ?SwooleServer $server = null;
    private Router $router;
    private ParameterResolver $parameterResolver;
    private Mapper $mapper;
    private bool $enableHttp = true;
    private bool $enableWs = true;
    private string $env;
    private array $globalMiddleware = [];
    private array $factories = [];
    private array $instances = [];
    private bool $running = false;
    private ?string $configuredHost = null;
    private ?int $configuredPort = null;
    private bool $enableLogging = true;
    private bool $debug = false;
    private Psr16CacheInterface $cache;

    private function __construct()
    {
        // Default to file-based cache
        $this->useFileCache();
        
        $this->router = new Router();
        $this->mapper = new Mapper();
        $this->parameterResolver = new ParameterResolver($this->mapper);
        $this->env = env('FASTOR_ENV', 'production');
        
        $this->registerDocRoutes();
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Pre-configure host/port before requiring the user's file.
     * The CLI uses this so $app->run() with no args still respects CLI flags.
     */
    public function configure(?string $host = null, ?int $port = null): self
    {
        $this->configuredHost = $host;
        $this->configuredPort = $port;
        return $this;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isDev(): bool
    {
        return $this->env === 'development' || $this->env === 'dev';
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function addMiddleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function disableHttp(): self
    {
        $this->enableHttp = false;
        return $this;
    }

    public function disableWs(): self
    {
        $this->enableWs = false;
        return $this;
    }

    public function disableLogging(): self
    {
        $this->enableLogging = false;
        Logger::disable();
        return $this;
    }

    public function enableDebug(): self
    {
        $this->debug = true;
        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function useCache(AdapterInterface $adapter): self
    {
        $this->cache = new Psr16Cache($adapter);
        if (isset($this->mapper)) {
            $this->mapper->reset();
        }
        return $this;
    }

    public function useFileCache(string $directory = 'cache'): self
    {
        return $this->useCache(new FilesystemAdapter('', 0, $directory));
    }

    public function useRedisCache(string $dsn = 'redis://localhost'): self
    {
        $client = RedisAdapter::createConnection($dsn);
        return $this->useCache(new RedisAdapter($client));
    }

    public function cache(): \Fastor\Cache\CacheConfigurator
    {
        return new \Fastor\Cache\CacheConfigurator($this);
    }

    public function getMapper(): Mapper
    {
        return $this->mapper;
    }

    public function getCache(): Psr16CacheInterface
    {
        return $this->cache;
    }

    public function registerDependency(string $name, callable $factory): self
    {
        $this->factories[$name] = $factory;
        return $this;
    }

    public function getFactory(string $name): ?callable
    {
        return $this->factories[$name] ?? null;
    }

    public function getDependency(string $name): mixed
    {
        return $this->instances[$name] ?? null;
    }

    public function setDependency(string $name, mixed $instance): void
    {
        $this->instances[$name] = $instance;
    }

    public function resolve(string $name): mixed
    {
        return $this->parameterResolver->resolveDependency($name, null);
    }

    // Proxy methods to Router
    public function get(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->get($path, $handler, $options);
        return $this;
    }

    public function post(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->post($path, $handler, $options);
        return $this;
    }

    public function put(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->put($path, $handler, $options);
        return $this;
    }

    public function patch(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->patch($path, $handler, $options);
        return $this;
    }

    public function delete(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->delete($path, $handler, $options);
        return $this;
    }

    public function websocket(string $path, callable $handler): self
    {
        $this->router->addWsRoute($path, $handler);
        return $this;
    }

    public function include(Router $router, string $prefix = ''): self
    {
        $this->router->includeRouter($router, $prefix);
        return $this;
    }

    /**
     * "Boutique Importer" - recursively loads PHP files in a directory.
     * If a file returns an array of classes, it treats them as entities and syncs them.
     */
    public function mount(string $directory, string $prefix = ''): void
    {
        $directory = rtrim($directory, '/');
        if (!is_dir($directory)) return;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->includeFile($file->getPathname(), $prefix);
            }
        }
    }

    private function includeFile(string $file, string $prefix = ''): mixed
    {
        // Inject $app into the included file scope
        $app = $this;
        $params = [$app, $prefix];
        
        $result = include $file;

        if (is_callable($result)) {
            return $result($this, ...$params);
        }

        return $result;
    }


    public function boot(): void
    {
        // 1. Warm up mapper
        $this->mapper->boot();

        // 2. Warm up reflection and validation plans for all routes
        foreach ($this->router->getRoutes() as $route) {
            $handler = $route['handler'];
            
            // Warm up handler metadata
            $metadata = ReflectionCache::getHandlerMetadata($handler);

            // Warm up Request DTOs
            foreach ($metadata['params'] as $param) {
                if ($param['type'] && class_exists($param['type']) && !str_starts_with($param['type'], 'Fastor\\')) {
                    ReflectionCache::getClassMetadata($param['type']);
                    $this->mapper->getValidationPlan($param['type']);
                }
            }

            // Warm up Response DTOs
            $handlerAttributes = $metadata['attributes'] ?? [];
            foreach ($handlerAttributes as $attr) {
                if ($attr instanceof \Fastor\Attributes\Response) {
                    ReflectionCache::getClassMetadata($attr->model);
                    $this->mapper->getValidationPlan($attr->model);
                }
            }
        }
    }

    private function registerDocRoutes(): void
    {
        $this->get('/openapi.json', function () {
            $generator = new OpenApiGenerator();
            return $generator->generate($this->router->getRoutes());
        });

        $this->get('/docs', function () {
            return $this->getSwaggerHtml();
        });
    }

    private function getSwaggerHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fastor API - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" >
    <style>
      html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
      *, *:before, *:after { box-sizing: inherit; }
      body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"> </script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"> </script>
    <script>
    window.onload = function() {
      const ui = SwaggerUIBundle({
        url: "/openapi.json",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
      });
      window.ui = ui;
    };
    </script>
</body>
</html>
HTML;
    }


    public function handleVirtualRequest(FastorRequest $request): FastorResponse
    {
        // Mock a Swoole response to capture output
        $swooleResponse = new class {
            public int $status = 200;
            public array $headers = [];
            public string $content = '';
            public function status(int $code) { $this->status = $code; }
            public function header(string $key, string $val) { $this->headers[$key] = $val; }
            public function end(string $content = '') { $this->content = $content; }
            public function write(string $content) { $this->content .= $content; }
        };

        // We need a FastorResponse that wraps our mock
        $response = new FastorResponse($swooleResponse);

        // Ensure ParameterResolver is initialized
        if (!$this->parameterResolver) {
            $this->parameterResolver = new ParameterResolver($this->mapper);
        }

        $this->handleRequest($request, $response);

        return $response;
    }

    public function run(?string $host = null, ?int $port = null, string $mode = 'http'): void
    {
        $host = $host ?? $this->configuredHost ?? env('FASTOR_HOST', '0.0.0.0');
        $port = $port ?? $this->configuredPort ?? (int)env('PORT', 8000);
        $this->running = true;

        $this->boot();

        $this->server = new SwooleServer($host, $port);

        $this->server->set([
            'worker_num' => 4,
            'enable_coroutine' => true,
            'task_worker_num' => 4,
            'enable_reuse_port' => true,
        ]);

        $this->server->on('Start', function (SwooleServer $server) use ($host, $port) {
            $protocols = [];
            if ($this->enableHttp) $protocols[] = 'HTTP';
            if ($this->enableWs) $protocols[] = 'WS';
            $protoStr = implode(' & ', $protocols);
            
            echo "Fastor server [$protoStr] started at http://$host:$port\n";
        });

        $this->server->on('WorkerStart', function (SwooleServer $server, int $workerId) {
            // Re-connect logic should be handled by individual DB drivers
        });

        if ($this->enableHttp) {
            $this->server->on('Request', function (SwooleRequest $request, SwooleResponse $response) {
                $fastorRequest = new FastorRequest($request);
                $fastorResponse = new FastorResponse($response);
                $this->handleRequest($fastorRequest, $fastorResponse);
            });
        }

        if ($this->enableWs) {
            $this->server->on('Open', function (SwooleServer $server, SwooleRequest $request) {
                $fastorRequest = new FastorRequest($request);
                $this->router->dispatchWs('/', $fastorRequest, $this->parameterResolver, $server);
            });

            $this->server->on('Message', function (SwooleServer $server, \OpenSwoole\WebSocket\Frame $frame) {
                $fastorFrame = new \Fastor\WebSocket\Frame($frame);
                $this->router->dispatchWs('/', $fastorFrame, $this->parameterResolver, $server);
            });
        }

        // Background Tasks
        $this->server->on('Task', function (SwooleServer $server, int $taskId, int $srcWorkerId, mixed $data) {
            if (is_callable($data)) {
                $data();
            }
        });

        $this->server->start();
    }

    private function handleRequest(FastorRequest $request, FastorResponse $response): void
    {
        if ($this->enableLogging) {
            Logger::info("Request: {$request->method()} {$request->uri()}");
        }

        $coreHandler = function ($request) use ($response) {
            try {
                $routeResult = $this->router->dispatch(
                    $request->method(),
                    $request->uri(),
                    $request,
                    $response,
                    $this->parameterResolver,
                    $this->server
                );

                $data = $routeResult['data'];
                if ($data instanceof FastorResponse) {
                    return $data;
                }

                return $response->json($data);

            } catch (HttpException $e) {
                return $response->status($e->getCode())->json([
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
            } catch (\Fastor\Exceptions\ValidationException $e) {
                return $response->status(422)->json([
                    'error' => 'Validation Failed',
                    'details' => $e->getErrors()
                ]);
            } catch (\Throwable $e) {
                if ($this->enableLogging) {
                    Logger::error("Uncaught Exception: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                }
                return $response->status(500)->json([
                    'error' => 'Internal Server Error',
                    'message' => $this->debug ? $e->getMessage() : null
                ]);
            }
        };

        $stack = new \Fastor\Middleware\MiddlewareStack();
        foreach ($this->globalMiddleware as $mw) {
            $stack->add($mw);
        }

        $finalResponse = $stack->handle($request, $coreHandler);
        
        // Final fallback if middleware returned something else
        if (!$finalResponse instanceof FastorResponse) {
             if (is_array($finalResponse) || is_object($finalResponse)) {
                 $response->json($finalResponse);
             } else {
                 $response->end((string)$finalResponse);
             }
        }
    }
}
