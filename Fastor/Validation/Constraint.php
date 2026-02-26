<?php

namespace Fastor\Validation;

interface Constraint
{
    /**
     * Validate the given value.
     * 
     * @param mixed $value The value to validate.
     * @param string $property The name of the property being validated.
     * @return string|null Error message if validation fails, null otherwise.
     */
    public function validate(mixed $value, string $property): ?string;
}
