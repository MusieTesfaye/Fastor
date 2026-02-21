<?php

namespace Fastor\Validation\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Max {
    public function __construct(public int|float $value) {}
}
