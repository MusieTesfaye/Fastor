<?php

namespace Fastor\Validation;

use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Mapper\MappingError;
use Fastor\Exceptions\ValidationException;

class Mapper
{
    private MapperBuilder $builder;

    public function __construct()
    {
        $this->builder = new MapperBuilder();
    }

    public function map(string $className, mixed $source): mixed
    {
        try {
            $object = $this->builder->mapper()->map($className, $source);
            $this->validate($object);
            return $object;
        } catch (MappingError $e) {
            $errors = [];
            foreach ($e->node()->messages() as $message) {
                $errors[] = [
                    'path' => $message->node()->path(),
                    'message' => $message->toString()
                ];
            }
            throw new ValidationException($errors ?: $e->getMessage(), 422);
        }
    }

    private function validate(object $object): void
    {
        $className = get_class($object);
        $metadata = \Fastor\ReflectionCache::getClassMetadata($className);
        $errors = [];

        foreach ($metadata['properties'] as $propName) {
            $reflectionProp = new \ReflectionProperty($className, $propName);
            $reflectionProp->setAccessible(true);
            $value = $reflectionProp->getValue($object);

            foreach ($reflectionProp->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Attributes\Email) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = ['path' => $propName, 'message' => "Invalid email format"];
                    }
                }

                if ($instance instanceof Attributes\Min) {
                    if ($value < $instance->value) {
                        $errors[] = ['path' => $propName, 'message' => "Must be at least {$instance->value}"];
                    }
                }

                if ($instance instanceof Attributes\Max) {
                    if ($value > $instance->value) {
                        $errors[] = ['path' => $propName, 'message' => "Must be no more than {$instance->value}"];
                    }
                }

                if ($instance instanceof Attributes\Range) {
                    if ($value < $instance->min || $value > $instance->max) {
                        $errors[] = ['path' => $propName, 'message' => "Must be between {$instance->min} and {$instance->max}"];
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 422);
        }
    }

    public function builder(): MapperBuilder
    {
        return $this->builder;
    }
}
