<?php

namespace Fastor;

use OpenSwoole\Http\Server as SwooleServer;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use FastRoute\Dispatcher;
use Fastor\Http\Request as FastorRequest;
use Fastor\Http\Response as FastorResponse;
use Fastor\Validation\Mapper;
use Fastor\Exceptions\HttpException;
use Fastor\Logging\Logger;
use Fastor\Database\Connection;

class App
{
    private static ?App $instance = null;
    private ?SwooleServer $server = null;
    private Router $router;
    private ParameterResolver $parameterResolver;
    private Mapper $mapper;
    private bool $isHotReload = false;
    private string $env;
    private array $globalMiddleware = [];

    private function __construct()
    {
        $this->router = new Router();
        $this->mapper = new Mapper();
        $this->parameterResolver = new ParameterResolver($this->mapper);
        $this->env = env('FASTOR_ENV', 'production');
        
        $this->registerDocRoutes();
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
        \Fastor\Database\Connection::reset();
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
                $result = $this->includeFile($file->getPathname(), $prefix);
                
                // If it returned an array of classes, assume they are entities
                if (is_array($result) && !empty($result) && is_string($result[0]) && class_exists($result[0])) {
                    $this->sync($result);
                }
            }
        }
    }

    private function includeFile(string $file, string $prefix = ''): mixed
    {
        // Inject $app into the included file scope
        $app = $this;
        $params = [$app, $prefix];
        
        $result = include $file;

        if (is_array($result) && !empty($result) && is_string($result[0]) && class_exists($result[0])) {
            $this->sync($result);
            return $result;
        }

        if (is_callable($result)) {
            return $result($this, ...$params);
        }

        return $result;
    }

    public function save(object $entity): void
    {
        (new \Cycle\ORM\EntityManager($this->orm()))->persist($entity)->run();
    }

    public function find(string $entity, mixed $id): ?object
    {
        return $this->orm()->getRepository($entity)->findByPK($id);
    }

    public function select(string $entity): \Fastor\Database\Query
    {
        return new \Fastor\Database\Query($this->orm()->getRepository($entity)->select());
    }

    public function all(string $entity): array
    {
        return $this->orm()->getRepository($entity)->findAll();
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

    public function sync(array $entities): void
    {
        Connection::sync($entities);
    }

    public function orm(): \Cycle\ORM\ORM
    {
        return Connection::orm();
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

    public function run(?string $host = '0.0.0.0', ?int $port = null): void
    {
        $host = $host ?? '0.0.0.0';
        $port = $port ?? (int)env('PORT', 8000);
        $this->server = new SwooleServer($host, $port);

        $this->server->set([
            'worker_num' => 4,
            'enable_coroutine' => true,
            'task_worker_num' => 4,
        ]);

        $this->server->on('Start', function (SwooleServer $server) use ($host, $port) {
            echo "Fastor server started at http://$host:$port\n";
            if ($this->isHotReload) {
                echo "Hot Reload active ⚡\n";
            }
        });

        $this->server->on('WorkerStart', function (SwooleServer $server, int $workerId) {
            // Re-connect DB in each worker
            Connection::reconnect();
        });

        $this->server->on('Request', function (SwooleRequest $request, SwooleResponse $response) {
            $fastorRequest = new FastorRequest($request);
            $fastorResponse = new FastorResponse($response);
            $this->handleRequest($fastorRequest, $fastorResponse);
        });

        // Background Tasks
        $this->server->on('Task', function (SwooleServer $server, int $taskId, int $srcWorkerId, mixed $data) {
            if (is_callable($data)) {
                $data();
            }
        });

        $this->server->on('Finish', function (SwooleServer $server, int $taskId, mixed $data) {
            // Optional finish handler
        });

        // WebSocket handling
        $this->server->on('Open', function (SwooleServer $server, SwooleRequest $request) {
            $fastorRequest = new FastorRequest($request);
            $this->router->dispatchWs('/', $fastorRequest, $this->parameterResolver, $server);
        });

        $this->server->on('Message', function (SwooleServer $server, \OpenSwoole\WebSocket\Frame $frame) {
            $fastorFrame = new \Fastor\WebSocket\Frame($frame);
            $this->router->dispatchWs('/', $fastorFrame, $this->parameterResolver, $server);
        });

        $this->server->start();
    }

    private function handleRequest(FastorRequest $request, FastorResponse $response): void
    {
        Logger::info("Request: {$request->method()} {$request->uri()}");

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
                Logger::error("Uncaught Exception: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                return $response->status(500)->json([
                    'error' => 'Internal Server Error',
                    'message' => $this->isDev() ? $e->getMessage() : null
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
