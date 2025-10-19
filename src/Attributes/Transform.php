<?php

namespace Mycro\Core\Attributes;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Transform
{
    public function __construct(public readonly string $transformerClass)
    {
    }
}
