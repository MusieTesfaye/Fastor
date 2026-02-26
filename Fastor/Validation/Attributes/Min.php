<?php

namespace Fastor\Validation\Attributes;

use Fastor\Validation\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Min implements Constraint
{
    public function __construct(public int|float $value) {}

    public function validate(mixed $value, string $property): ?string
    {
        $check = is_string($value) ? strlen($value) : $value;
        if ($check < $this->value) {
            return is_string($value) 
                ? "Must be at least {$this->value} characters" 
                : "Must be at least {$this->value}";
        }
        return null;
    }
}
