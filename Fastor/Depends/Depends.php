<?php

namespace Fastor\Depends;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Depends
{
    public function __construct(
        public string $name
    ) {}
}
