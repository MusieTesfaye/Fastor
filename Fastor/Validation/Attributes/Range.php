<?php

namespace Fastor\Validation\Attributes;

use Fastor\Validation\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Range implements Constraint
{
    public function __construct(public int|float $min, public int|float $max) {}

    public function validate(mixed $value, string $property): ?string
    {
        $check = is_string($value) ? strlen($value) : $value;
        if ($check < $this->min || $check > $this->max) {
            return is_string($value)
                ? "Must be between {$this->min} and {$this->max} characters"
                : "Must be between {$this->min} and {$this->max}";
        }
        return null;
    }
}
