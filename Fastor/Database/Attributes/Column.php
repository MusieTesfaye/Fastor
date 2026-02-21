<?php

namespace Fastor\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $type = 'string',
        public bool $primary = false,
        public ?bool $nullable = null,
        public mixed $default = null
    ) {}
}
