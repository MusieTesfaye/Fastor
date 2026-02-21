<?php

namespace Fastor;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;

class OpenApiGenerator
{
    public function generate(array $routes): array
    {
        $paths = [];
        $components = ['schemas' => []];

        foreach ($routes as $route) {
            $path = $route['path'];
            if (in_array($path, ['/openapi.json', '/docs'], true)) {
                continue;
            }

            $method = strtolower($route['method']);
            $handler = $route['handler'];
            $options = $route['options'] ?? [];
            
            $reflection = $this->getReflection($handler);
            $parameters = [];
            $requestBody = null;
            $responseSchema = null;
            $tags = [];
            $security = null;

            // Extract Tags explicitly if provided
            foreach ($reflection->getAttributes() as $attr) {
                if ($attr->getName() === 'Fastor\\Attributes\\Tag' || $attr->getShortName() === 'Tag') {
                    $tags[] = $attr->getArguments()[0];
                }
            }

            if (isset($options['response_model'])) {
                $responseModel = $options['response_model'];
                $schemaName = (new \ReflectionClass($responseModel))->getShortName();
                $responseSchema = ['$ref' => "#/components/schemas/$schemaName"];
                $this->addSchema($components['schemas'], $responseModel);
                if (empty($tags)) {
                    $tags[] = str_replace(['Schema', 'Create', 'Update', 'Read'], '', $schemaName);
                }
            }

            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

                // Check for Auth attribute or specific Auth drivers
                $isAuth = false;
                foreach ($parameter->getAttributes() as $attr) {
                    if ($attr->getName() === 'Fastor\\Attributes\\Auth' || $attr->getShortName() === 'Auth') {
                        $isAuth = true;
                    }
                }

                if ($isAuth || $typeName === 'Fastor\Auth\JWT') {
                    $security = [['BearerAuth' => []]];
                    $components['securitySchemes']['BearerAuth'] = [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ];
                } elseif ($typeName === 'Fastor\Auth\ApiKey') {
                    $security = [['ApiKeyAuth' => []]];
                    $components['securitySchemes']['ApiKeyAuth'] = [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key'
                    ];
                }

                if (!$type instanceof ReflectionNamedType) continue;

                $typeName = $type->getName();

                // Path variables
                if (str_contains($path, '{' . $parameter->getName() . '}')) {
                    $parameters[] = [
                        'name' => $parameter->getName(),
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => $this->mapTypeName($typeName)]
                    ];
                    continue;
                }

                // Special Fastor objects (Request, Response)
                if ($typeName === 'Fastor\Http\Request' || $typeName === 'Fastor\Http\Response' || $typeName === 'Fastor\App' || str_starts_with($typeName, 'Fastor\WebSocket\\')) {
                    continue;
                }

                // DTOs (classes) become requestBody
                if (class_exists($typeName) && !str_starts_with($typeName, 'OpenSwoole\\') && !str_starts_with($typeName, 'Fastor\\')) {
                    $schemaName = (new \ReflectionClass($typeName))->getShortName();
                    $requestBody = [
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/$schemaName"]
                            ]
                        ]
                    ];
                    $this->addSchema($components['schemas'], $typeName);
                    if (empty($tags)) {
                        $tags[] = str_replace(['Schema', 'Create', 'Update', 'Read'], '', $schemaName);
                    }
                    continue;
                }

                // Primitives become Query parameters
                if (in_array($typeName, ['int', 'float', 'string', 'bool', 'mixed'])) {
                    $parameters[] = [
                        'name' => $parameter->getName(),
                        'in' => 'query',
                        'required' => !$parameter->allowsNull() && !$parameter->isDefaultValueAvailable(),
                        'schema' => ['type' => $this->mapTypeName($typeName)]
                    ];
                    continue;
                }
            }

            if (empty($tags)) {
                $tags = ['default'];
            }

            $routeData = [
                'summary' => $path,
                'tags' => array_unique($tags),
                'parameters' => $parameters,
                'responses' => [
                    '200' => [
                        'description' => 'Successful Response',
                        'content' => $responseSchema ? [
                            'application/json' => [
                                'schema' => $responseSchema
                            ]
                        ] : [
                            'application/json' => [
                                'schema' => ['type' => 'object']
                            ]
                        ]
                    ]
                ]
            ];

            if ($requestBody) {
                $routeData['requestBody'] = $requestBody;
            }

            if ($security) {
                $routeData['security'] = $security;
            }

            $pathKey = preg_replace('/:[^\/]+/', '{$1}', $path); // Convert FastRoute to OpenAPI
            $paths[$pathKey][$method] = $routeData;
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Fastor API',
                'version' => '0.1.0'
            ],
            'paths' => $paths,
            'components' => $components
        ];
    }

    private function getReflection($handler): \ReflectionFunctionAbstract
    {
        if (is_callable($handler) && !is_string($handler) && !is_array($handler)) {
            return new ReflectionFunction($handler);
        }
        if (is_array($handler)) {
            return new ReflectionMethod($handler[0], $handler[1]);
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            return new ReflectionMethod($class, $method);
        }
        throw new \Exception('Could not reflect handler');
    }

    private function mapType(\ReflectionType $type, array &$schemas): array
    {
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();
            if (class_exists($typeName) && !str_starts_with($typeName, 'Fastor\\') && !str_starts_with($typeName, 'OpenSwoole\\')) {
                $schemaName = (new \ReflectionClass($typeName))->getShortName();
                $this->addSchema($schemas, $typeName);
                return ['$ref' => "#/components/schemas/$schemaName"];
            }
            return ['type' => $this->mapTypeName($typeName)];
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = $this->mapType($unionType, $schemas);
            }
            return ['anyOf' => $types];
        }

        return ['type' => 'string'];
    }

    private function mapTypeName(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    private function addSchema(array &$schemas, string $className): void
    {
        if (str_starts_with($className, 'Fastor\\')) return;
        
        $reflection = new \ReflectionClass($className);
        $schemaName = $reflection->getShortName();
        
        if (isset($schemas[$schemaName])) return;

        $properties = [];
        $required = [];

        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if ($type) {
                    $properties[$param->getName()] = $this->mapType($type, $schemas);
                    if (!$param->isOptional()) {
                        $required[] = $param->getName();
                    }
                }
            }
        }

        foreach ($reflection->getProperties() as $prop) {
            if (isset($properties[$prop->getName()])) continue;
            $type = $prop->getType();
            if ($type) {
                $properties[$prop->getName()] = $this->mapType($type, $schemas);
                if (!$type->allowsNull() && !$prop->hasDefaultValue()) {
                    $required[] = $prop->getName();
                }
            }
        }

        $schemas[$schemaName] = [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_unique($required)
        ];
    }
}
