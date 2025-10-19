<?php

namespace Mycro\Core\Contracts;

use Exception;
use Mycro\Core\Attributes\Transform;
use Mycro\Core\Services\DefaultPropertyMapper;
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

            $value = $this->applyTransformerIfExists($property, $value);

            $property->setValue($this, $value);
        }
    }

    /**
     * @throws DtoHydrationException
     * @throws Exception
     */
    protected function applyTransformerIfExists(ReflectionProperty $property, mixed $value): mixed
    {
        $attributes = $property->getAttributes(Transform::class);

        if (empty($attributes)) {
            return $value;
        }

        /** @var Transform $transformAttr */
        $transformAttr = $attributes[0]->newInstance();

        $transformerClass = $transformAttr->transformerClass;

        if (!class_exists($transformerClass)) {
            throw new Exception("Transformer class {$transformerClass} not found for property {$property->getName()}");
        }

        $transformer = new $transformerClass();

        if (!$transformer instanceof TransformerContract) {
            throw new Exception("Transformer {$transformerClass} must implement TransformerContract");
        }

        return $transformer->transform($value);
    }

}
