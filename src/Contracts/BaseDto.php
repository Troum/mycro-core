<?php

namespace Marketplace\Core\Contracts;

use App\Attributes\DefaultValue;
use App\Exceptions\DtoHydrationException;
use App\Exceptions\ReadonlyPropertyUpdateException;
use ReflectionClass;
use ReflectionProperty;

abstract class BaseDto
{
    /**
     * @throws ReadonlyPropertyUpdateException
     * @throws DtoHydrationException
     */
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    /**
     * @param array $data
     * @return void
     * @throws DtoHydrationException|ReadonlyPropertyUpdateException
     */
    private function hydrate(array $data): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_READONLY) as $property) {
            $name = $property->getName();

            if ($property->isInitialized($this)) {
                throw new ReadonlyPropertyUpdateException($name, static::class);
            }

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } else {
                $defaultAttr = $this->getDefaultAttribute($property);
                if ($defaultAttr !== null) {
                    $value = $defaultAttr->value;
                } else {
                    throw new DtoHydrationException($name, static::class);
                }
            }

            $property->setValue($this, $value);
        }
    }

    /**
     * @param ReflectionProperty $property
     * @return DefaultValue|null
     */
    private function getDefaultAttribute(ReflectionProperty $property): ?DefaultValue
    {
        $attributes = $property->getAttributes(DefaultValue::class);
        return count($attributes) > 0 ? $attributes[0]->newInstance() : null;
    }
}