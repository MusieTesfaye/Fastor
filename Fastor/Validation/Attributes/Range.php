<?php

namespace Fastor\Validation\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Range {
    public function __construct(public int|float $min, public int|float $max) {}
}
