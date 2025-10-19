<?php

namespace Marketplace\Core\Contracts;
use ReflectionProperty;
interface PropertyMapperInterface
{
    /**
     * @param object $dto
     * @param ReflectionProperty $property
     * @param array $data
     * @return array
     */
    public function resolve(object $dto, ReflectionProperty $property, array $data): array;
}
