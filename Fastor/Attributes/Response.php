<?php

namespace Fastor\Attributes;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
class Response
{
    public function __construct(
        public string $model
    ) {}
}
