<?php

namespace Fastor\Validation\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Min {
    public function __construct(public int|float $value) {}
}
