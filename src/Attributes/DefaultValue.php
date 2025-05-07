<?php

namespace Mycro\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(public mixed $value)
    {
    }
}
