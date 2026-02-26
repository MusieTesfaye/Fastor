<?php

namespace Fastor\Validation;

use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Mapper\MappingError;
use Fastor\Exceptions\ValidationException;

class Mapper
{
    private ?\CuyZ\Valinor\Mapper\TreeMapper $mapper = null;
    private array $validationPlans = [];

    public function __construct()
    {
    }

    private function getMapper(): \CuyZ\Valinor\Mapper\TreeMapper
    {
        if ($this->mapper === null) {
            $this->mapper = (new MapperBuilder())
                ->withCache(\Fastor\App::getInstance()->getCache())
                ->mapper();
        }
        return $this->mapper;
    }

    public function reset(): void
    {
        $this->mapper = null;
        $this->validationPlans = [];
    }

    public function boot(): void
    {
        $this->getMapper();
    }

    public function map(string $className, mixed $source): mixed
    {
        try {
            $object = $this->getMapper()->map($className, $source);
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
        $plan = $this->getValidationPlan($className);
        
        if (empty($plan)) {
            return;
        }

        $errors = [];
        foreach ($plan as $propName => $attributes) {
            $reflectionProp = \Fastor\ReflectionCache::getPropertyReflection($className, $propName);
            $reflectionProp->setAccessible(true);
            $value = $reflectionProp->getValue($object);

            foreach ($attributes as $instance) {
                if ($instance instanceof Constraint) {
                    if ($error = $instance->validate($value, $propName)) {
                        $errors[] = ['path' => $propName, 'message' => $error];
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 422);
        }
    }

    public function getValidationPlan(string $className): array
    {
        if (isset($this->validationPlans[$className])) {
            return $this->validationPlans[$className];
        }

        $metadata = \Fastor\ReflectionCache::getClassMetadata($className);
        $plan = [];

        foreach ($metadata['property_attributes'] as $propName => $attributes) {
            if (!empty($attributes)) {
                $plan[$propName] = $attributes;
            }
        }

        return $this->validationPlans[$className] = $plan;
    }

    public function builder(): MapperBuilder
    {
        return $this->builder;
    }
}
