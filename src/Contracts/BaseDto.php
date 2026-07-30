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
     * Имена свойств, ключи которых действительно присутствовали во входном массиве.
     * Свойство намеренно не readonly: hydrate() и toArray() обходят только
     * readonly-свойства, поэтому это поле не попадёт ни в проверку повторной
     * инициализации, ни в результат toArray().
     *
     * @var array<int, string>
     */
    private array $providedProperties = [];

    /**
     * @param PropertyMapperInterface $propertyMapper
     * @return void
     */
    public static function setPropertyMapper(PropertyMapperInterface $propertyMapper): void
    {
        self::$propertyMapper = $propertyMapper;
    }

    /**
     * @return PropertyMapperInterface
     */
    protected static function propertyMapper(): PropertyMapperInterface
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
     * Был ли ключ свойства передан во входном массиве. Явный null считается
     * переданным значением, значение из DefaultValue или required: false — нет.
     *
     * @param string $property
     * @return bool
     */
    public function wasProvided(string $property): bool
    {
        return in_array($property, $this->providedProperties, true);
    }

    /**
     * Только те свойства, ключи которых присутствовали во входном массиве.
     * Значения — уже после #[Transform], как и в toArray().
     *
     * Свойство, найденное по алиасу #[MapProperty(from: 'other_name')], попадает
     * в результат под именем свойства, а не под именем алиаса.
     *
     * @return array<string, mixed>
     */
    public function providedAttributes(): array
    {
        return array_intersect_key($this->toArray(), array_flip($this->providedProperties));
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

            $resolution = $mapper->resolve($this, $property, $data);
            [$hasValue, $value] = $resolution;

            if (!$hasValue) {
                throw new DtoHydrationException($name, static::class);
            }

            if ($resolution[2] ?? false) {
                $this->providedProperties[] = $name;
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
