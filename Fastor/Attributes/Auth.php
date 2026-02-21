<?php

namespace Fastor\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Auth
{
    public function __construct() {}
}
