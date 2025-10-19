<?php

namespace Marketplace\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MapProperty
{
    /**
     * @param string|array $from
     * @param bool $required
     */
    public function __construct(
        public readonly string|array $from,
        public readonly bool $required = true
    ) {}
}
