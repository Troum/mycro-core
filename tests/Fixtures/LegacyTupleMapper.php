<?php

namespace Mycro\Core\Tests\Fixtures;

use Mycro\Core\Contracts\PropertyMapperInterface;
use Mycro\Core\Services\DefaultPropertyMapper;
use ReflectionProperty;

/**
 * Сторонний маппер, написанный до появления третьего элемента кортежа.
 */
final class LegacyTupleMapper implements PropertyMapperInterface
{
    private DefaultPropertyMapper $inner;

    public function __construct()
    {
        $this->inner = new DefaultPropertyMapper();
    }

    public function resolve(object $dto, ReflectionProperty $property, array $data): array
    {
        [$hasValue, $value] = $this->inner->resolve($dto, $property, $data);

        return [$hasValue, $value];
    }
}
