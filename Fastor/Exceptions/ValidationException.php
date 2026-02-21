<?php

namespace Fastor\Exceptions;

class ValidationException extends \Exception
{
    private array $errors = [];

    public function __construct(string|array $errors, int $code = 422)
    {
        if (is_array($errors)) {
            $this->errors = $errors;
            parent::__construct("Validation failed", $code);
        } else {
            $this->errors = [['message' => $errors]];
            parent::__construct($errors, $code);
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
