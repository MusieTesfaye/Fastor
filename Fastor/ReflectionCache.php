<?php

namespace Fastor;

/**
 * High-performance internal cache for reflection metadata.
 * Drastically reduces overhead in hot paths like parameter resolution and data filtering.
 */
class ReflectionCache
{
    private static array $reflections = [];
    private static array $handlers = [];
    private static array $classes = [];
    private static array $properties = [];

    public static function getHandlerMetadata(callable|string|array $handler): array
    {
        $key = self::getHandlerKey($handler);
        
        // Local process cache (fastest, supports objects)
        if (isset(self::$handlers[$key])) {
            return self::$handlers[$key];
        }

        // Persistent cache (slower, but survives restarts/workers)
        $cache = App::getInstance()->getCache();
        $cached = $cache->get("handler_meta_$key");
        if ($cached) {
            return self::$handlers[$key] = $cached;
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

        $metadata = [
            'params' => $params,
            'return_type' => $reflection->getReturnType() instanceof \ReflectionNamedType ? $reflection->getReturnType()->getName() : null,
            'attributes' => self::extractHandlerAttributes($reflection)
        ];

        $cache->set("handler_meta_$key", $metadata);
        
        // Store reflection object locally only
        self::$reflections["ref_$key"] = $reflection;

        return self::$handlers[$key] = $metadata;
    }

    public static function getHandlerReflection(callable|string|array $handler): \ReflectionFunction|\ReflectionMethod
    {
        $key = self::getHandlerKey($handler);
        return self::$reflections["ref_$key"] ??= self::reflect($handler);
    }

    public static function getClassMetadata(string $className): array
    {
        if (isset(self::$classes[$className])) {
            return self::$classes[$className];
        }

        $cache = App::getInstance()->getCache();
        $cached = $cache->get("class_meta_$className");
        if ($cached) {
            return self::$classes[$className] = $cached;
        }

        $reflection = new \ReflectionClass($className);
        $properties = [];
        $propertyAttributes = [];
        
        // Constructor params (for DTOs)
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $properties[] = $param->getName();
            }
        }

        // Public properties & their validation attributes
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $propName = $prop->getName();
            $properties[] = $propName;
            
            $attrs = [];
            foreach ($prop->getAttributes() as $attr) {
                $attrs[] = $attr->newInstance();
            }
            $propertyAttributes[$propName] = $attrs;
        }

        $metadata = [
            'properties' => array_unique($properties),
            'property_attributes' => $propertyAttributes,
            'methods' => get_class_methods($className)
        ];

        $cache->set("class_meta_$className", $metadata);

        return self::$classes[$className] = $metadata;
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
    private static function extractHandlerAttributes(\ReflectionFunction|\ReflectionMethod $reflection): array
    {
        $attrs = [];
        foreach ($reflection->getAttributes() as $attr) {
            $attrs[] = $attr->newInstance();
        }
        return $attrs;
    }

    public static function getPropertyReflection(string $className, string $propertyName): \ReflectionProperty
    {
        $key = "$className::$propertyName";
        return self::$properties[$key] ??= new \ReflectionProperty($className, $propertyName);
    }
}
