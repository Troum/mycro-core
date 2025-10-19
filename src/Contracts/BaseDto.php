<?php

namespace Mycro\Core\Contracts;

use Marketplace\Core\Contracts\PropertyMapperInterface;
use Marketplace\Core\Services\DefaultPropertyMapper;
use Mycro\Core\Exceptions\DtoHydrationException;
use Mycro\Core\Exceptions\ReadonlyPropertyUpdateException;
use ReflectionClass;
use ReflectionProperty;

abstract class BaseDto
{
    /**
     * @var PropertyMapperInterface|null
     */
    private static ?PropertyMapperInterface $propertyMapper = null;

    /**
     * @param PropertyMapperInterface $propertyMapper
     * @return void
     */
    public static function setPropertyMapper(PropertyMapperInterface $propertyMapper): void
    {
        self::$propertyMapper = $propertyMapper;
    }

    /**
     * @return DefaultPropertyMapper
     */
    protected static function propertyMapper(): DefaultPropertyMapper
    {
        return self::$propertyMapper ??= new DefaultPropertyMapper();
    }

    /**
     * @throws ReadonlyPropertyUpdateException
     * @throws DtoHydrationException
     */
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $result = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_READONLY) as $property) {
            $result[$property->getName()] = $property->getValue($this);
        }

        return $result;
    }

    /**
     * @param array $data
     * @return void
     * @throws DtoHydrationException|ReadonlyPropertyUpdateException
     */
    private function hydrate(array $data): void
    {
        $mapper = static::propertyMapper();
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_READONLY) as $property) {
            $name = $property->getName();

            if ($property->isInitialized($this)) {
                throw new ReadonlyPropertyUpdateException($name, static::class);
            }

            [$hasValue, $value] = $mapper->resolve($this, $property, $data);

            if (!$hasValue) {
                throw new DtoHydrationException($name, static::class);
            }

            $property->setValue($this, $value);
        }
    }
}
