<?php

namespace Fastor\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min {
    public function __construct(public int|float $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max {
    public function __construct(public int|float $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Range {
    public function __construct(public int|float $min, public int|float $max) {}
}
