<?php

namespace Fastor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    public function __construct() {}
}
