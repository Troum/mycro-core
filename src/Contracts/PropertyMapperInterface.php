<?php

namespace Mycro\Core\Contracts;
use ReflectionProperty;
interface PropertyMapperInterface
{
    /**
     * Третий элемент кортежа — позиционный и необязательный: маппер, возвращающий
     * два элемента, остаётся совместимым, но BaseDto не сможет отличить переданный
     * ключ от значения по умолчанию.
     *
     * @param object $dto
     * @param ReflectionProperty $property
     * @param array $data
     * @return array{0: bool, 1: mixed, 2?: bool} [$hasValue, $value, $fromInput]
     */
    public function resolve(object $dto, ReflectionProperty $property, array $data): array;
}
