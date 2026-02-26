<?php

namespace Fastor;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Fastor\Validation\Mapper;
use Fastor\BackgroundTasks;
use Fastor\Attributes\Query;
use Fastor\Attributes\Header;
use Fastor\Attributes\Cookie;
use Fastor\Attributes\Body;
use Fastor\Attributes\Auth;
use Fastor\WebSocket\Broadcast;
use Fastor\Exceptions\ValidationException;
use Fastor\Exceptions\HttpException;
use Fastor\Http\Request;
use Fastor\WebSocket\Frame;
use OpenSwoole\Http\Server as SwooleServer;
use OpenSwoole\WebSocket\Server as SwooleWsServer;

class ParameterResolver
{
    private Mapper $mapper;
    private ?SwooleServer $server = null;
    private ?\Fastor\Http\Response $response = null;

    public function __construct(Mapper $mapper, ?SwooleServer $server = null)
    {
        $this->mapper = $mapper;
        $this->server = $server;
    }

    public function setResponse(\Fastor\Http\Response $response): void
    {
        $this->response = $response;
    }

    public function resolve($handler, array $pathVars, $request): array
    {
        $metadata = ReflectionCache::getHandlerMetadata($handler);
        $resolvedArgs = [];

        foreach ($metadata['params'] as $param) {
            $resolvedArgs[] = $this->resolveParameter($param, $pathVars, $request);
        }

        return $resolvedArgs;
    }

    private function resolveParameter(array $param, array $pathVars, $request): mixed
    {
        $name = $param['name'];
        $typeName = $param['type'];

        // 0. Check for Elite Attributes (Query, Header, Cookie, Body, Auth)
        foreach ($param['attributes'] as $attr) {
            $val = null;
            $found = false;
            
            if ($attr instanceof Query) {
                $key = $attr->name ?: $name;
                $val = $this->castValue($request->query($key), $typeName);
                $found = true;
            } elseif ($attr instanceof Header) {
                $key = $attr->name ?: $name;
                $val = $this->castValue($request->header($key), $typeName);
                $found = true;
            } elseif ($attr instanceof Cookie) {
                $key = $attr->name ?: $name;
                $val = $this->castValue($request->cookie($key), $typeName);
                $found = true;
            } elseif ($attr instanceof Body) {
                $val = $this->resolveDTO($typeName, $request);
                $found = true;
            } elseif ($attr instanceof Auth) {
                $val = $this->resolveDependency('auth', $request);
                $found = true;
            } elseif ($attr instanceof \Fastor\Depends\Depends) {
                $val = $this->resolveDependency($attr->name, $request);
                $found = true;
            }

            if ($found) {
                if ($val === null) {
                    if ($param['has_default']) {
                        return $param['default'];
                    }
                    if ($param['allows_null']) {
                        return null;
                    }
                    throw new HttpException(422, "Missing required parameter: $name");
                }
                return $val;
            }
        }

        // 1. Check path variables
        if (isset($pathVars[$name])) {
            $value = $pathVars[$name];
            return $this->castValue($value, $typeName);
        }

        if ($typeName === 'Fastor\Http\Request') {
            return $request;
        }
        if ($typeName === 'Fastor\Http\Response') {
            return $this->response;
        }
        if ($typeName === 'Fastor\App') {
            return App::getInstance();
        }

        // 4. Check for special OpenSwoole objects (Request, Frame)
        if ($typeName === 'OpenSwoole\Http\Request' && $request instanceof Request) {
            return $request->swoole();
        }
        if ($typeName === 'OpenSwoole\WebSocket\Frame' && $request instanceof Frame) {
            return $request->swoole();
        }
        if ($typeName === 'Fastor\WebSocket\Frame') {
            return $request;
        }

        // 4.5 Check for BackgroundTasks
        if (($typeName === BackgroundTasks::class || $typeName === 'Fastor\BackgroundTasks') && $this->server) {
            return new BackgroundTasks($this->server);
        }

        // 4.6 Check for Broadcast
        if (($typeName === Broadcast::class || $typeName === 'Fastor\WebSocket\Broadcast') && ($this->server instanceof SwooleWsServer || $this->server instanceof SwooleServer)) {
            return new Broadcast($this->server);
        }

        // Deprecated hardcoded drivers removed in favor of generic dependencies

        // 5. Try resolving as a Dependency (Auto-wiring) or DTO
        if ($typeName && class_exists($typeName) && !str_starts_with($typeName, 'OpenSwoole\\') && !str_starts_with($typeName, 'Fastor\\')) {
            // If it's registered or has a constructor, try auto-wiring first
            $app = App::getInstance();
            if ($app->getFactory($typeName) || (new \ReflectionClass($typeName))->getConstructor() !== null) {
                try {
                    return $this->resolveDependency($typeName, $request);
                } catch (\Throwable $e) {
                    // If auto-wiring fails, it might be intended as a DTO (e.g. constructor params are not services)
                    // Fallback to DTO mapping
                }
            }
            
            return $this->resolveDTO($typeName, $request);
        }

        // 6. Auto-infer primitives as Query Parameters (FastAPI style)
        if ($request instanceof Request) {
            if ($typeName === null || in_array($typeName, ['int', 'float', 'string', 'bool', 'mixed'])) {
                $val = $request->query($name);
                if ($val !== null) {
                    return $this->castValue($val, $typeName);
                }
            }
        }

        // 7. Default value
        if ($param['has_default']) {
            return $param['default'];
        }

        if ($param['allows_null']) {
            return null;
        }

        throw new HttpException(422, "Missing required parameter: $name");
    }

    private function resolveDTO(string $className, $request): mixed
    {
        if (!$request instanceof Request) {
            return null;
        }
        
        $data = $request->json() ?: $request->post() ?: [];
        
        try {
            return $this->mapper->map($className, $data);
        } catch (\Fastor\Exceptions\ValidationException $e) {
            throw $e;
        } catch (\Cuyz\Valinor\Mapper\MappingError $e) {
            $errors = [];
            foreach ($e->node()->errors() as $error) {
                $errors[$error->node()->name()] = $error->message();
            }
            throw new ValidationException($errors);
        } catch (\Throwable $e) {
            throw new HttpException(422, "Processing Failed (" . get_class($e) . "): " . $e->getMessage());
        }
    }

    public function resolveDependency(string $name, $request): mixed
    {
        $app = \Fastor\App::getInstance();
        
        // 1. Check for already resolved instance (singleton)
        if ($instance = $app->getDependency($name)) {
            return $instance;
        }

        // 2. Check for manual factory
        $factory = $app->getFactory($name);
        if ($factory) {
            $instance = $factory($request);
            $app->setDependency($name, $instance);
            return $instance;
        }

        // 3. Auto-wiring for concrete classes
        if (class_exists($name)) {
            $reflection = new \ReflectionClass($name);
            if (!$reflection->isInstantiable()) {
                throw new HttpException(500, "Dependency '$name' is not a registered factory and is not instantiable.");
            }

            $constructor = $reflection->getConstructor();
            if (!$constructor) {
                $instance = new $name();
                $app->setDependency($name, $instance);
                return $instance;
            }

            $params = $constructor->getParameters();
            $args = [];
            foreach ($params as $param) {
                $paramType = $param->getType();
                if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                    $args[] = $this->resolveDependency($paramType->getName(), $request);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new HttpException(500, "Unresolved dependency '{$param->getName()}' in constructor of $name");
                }
            }

            $instance = $reflection->newInstanceArgs($args);
            $app->setDependency($name, $instance);
            return $instance;
        }

        throw new HttpException(500, "Dependency '$name' not found and cannot be auto-wired.");
    }

    private function castValue($value, ?string $type): mixed
    {
        if ($value === null) return null;
        if ($type === null || $type === 'mixed') return $value;
        
        if ($type === 'int') {
            if (!is_numeric($value)) {
                throw new HttpException(422, "Parameter must be an integer");
            }
            return (int)$value;
        }

        if ($type === 'float') {
            if (!is_numeric($value)) {
                throw new HttpException(422, "Parameter must be a number");
            }
            return (float)$value;
        }

        if ($type === 'bool') {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered === null) {
                throw new HttpException(422, "Parameter must be a boolean");
            }
            return $filtered;
        }

        if ($type === 'string') {
            return (string)$value;
        }

        return $value;
    }
}
