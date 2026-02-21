<?php

namespace Fastor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Header
{
    public function __construct(public ?string $name = null) {}
}
