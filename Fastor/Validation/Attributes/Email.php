<?php

namespace Fastor\Validation\Attributes;

use Fastor\Validation\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Email implements Constraint
{
    public function validate(mixed $value, string $property): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format";
        }
        return null;
    }
}
