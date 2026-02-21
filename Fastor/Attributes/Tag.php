<?php

namespace Fastor\Attributes;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
class Tag
{
    public function __construct(public string $name)
    {
    }
}
