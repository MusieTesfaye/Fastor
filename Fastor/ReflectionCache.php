<?php

namespace Fastor;

/**
 * High-performance internal cache for reflection metadata.
 * Drastically reduces overhead in hot paths like parameter resolution and data filtering.
 */
class ReflectionCache
{
    private static array $handlers = [];
    private static array $classes = [];

    public static function getHandlerMetadata(callable|string|array $handler): array
    {
        $key = self::getHandlerKey($handler);
        if (isset(self::$handlers[$key])) {
            return self::$handlers[$key];
        }

        $reflection = self::reflect($handler);
        $params = [];
        foreach ($reflection->getParameters() as $param) {
            $params[] = [
                'name' => $param->getName(),
                'type' => $param->getType() ? $param->getType()->getName() : null,
                'allows_null' => $param->allowsNull(),
                'has_default' => $param->isDefaultValueAvailable(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'attributes' => self::extractAttributes($param)
            ];
        }

        return self::$handlers[$key] = [
            'reflection' => $reflection,
            'params' => $params
        ];
    }

    public static function getClassMetadata(string $className): array
    {
        if (isset(self::$classes[$className])) {
            return self::$classes[$className];
        }

        $reflection = new \ReflectionClass($className);
        $properties = [];
        
        // Constructor params (for DTOs)
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $properties[] = $param->getName();
            }
        }

        // Public properties
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $properties[] = $prop->getName();
        }

        return self::$classes[$className] = [
            'properties' => array_unique($properties),
            'methods' => get_class_methods($className)
        ];
    }

    private static function getHandlerKey($handler): string
    {
        if (is_string($handler)) return $handler;
        if (is_array($handler)) return (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1];
        if ($handler instanceof \Closure) return spl_object_hash($handler);
        return 'unknown';
    }

    private static function reflect($handler): \ReflectionFunction|\ReflectionMethod
    {
        if ($handler instanceof \Closure || (is_string($handler) && function_exists($handler))) {
            return new \ReflectionFunction($handler);
        }
        if (is_array($handler)) {
            return new \ReflectionMethod($handler[0], $handler[1]);
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            return new \ReflectionMethod($class, $method);
        }
        throw new \Exception("Unsupported handler type");
    }

    private static function extractAttributes(\ReflectionParameter $param): array
    {
        $attrs = [];
        foreach ($param->getAttributes() as $attr) {
            $attrs[] = $attr->newInstance();
        }
        return $attrs;
    }
}
