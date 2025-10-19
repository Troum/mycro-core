<?php

namespace Mycro\Core\Services;

use Mycro\Core\Attributes\MapProperty;
use Mycro\Core\Contracts\PropertyMapperInterface;
use Mycro\Core\Attributes\DefaultValue;
use ReflectionProperty;

class DefaultPropertyMapper implements PropertyMapperInterface
{

    /**
     * @inheritDoc
     */
    public function resolve(object $dto, ReflectionProperty $property, array $data): array
    {
        $normalizedData = $this->normalizeArrayKeys($data);

        $name = $property->getName();
        $mapAttribute = $this->getAttribute($property, MapProperty::class);

        $aliases = $mapAttribute
            ? $this->normalizeAliases($mapAttribute->from)
            : [$name];

        foreach ($aliases as $alias) {
            $alias = $this->toSnakeCase($alias);
            if (array_key_exists($alias, $normalizedData)) {
                return [true, $normalizedData[$alias]];
            }
        }

        if (array_key_exists($name, $normalizedData)) {
            return [true, $normalizedData[$name]];
        }

        $defaultAttribute = $this->getAttribute($property, DefaultValue::class);

        if ($defaultAttribute !== null) {
            return [true, $defaultAttribute->value];
        }

        if ($mapAttribute && $mapAttribute->required === false) {
            return [true, null];
        }

        return [false, null];
    }

    /**
     * @param ReflectionProperty $property
     * @param string $class
     * @return object|null
     */
    private function getAttribute(ReflectionProperty $property, string $class): ?object
    {
        $attributes = $property->getAttributes($class);
        return count($attributes) > 0 ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param string|array $from
     * @return array
     */
    private function normalizeAliases(string|array $from): array
    {
        $aliases = is_array($from) ? $from : [$from];
        return array_map(fn($a) => $this->toSnakeCase($a), $aliases);
    }

    /**
     * @param array $data
     * @return array
     */
    private function normalizeArrayKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $newKey = $this->toSnakeCase($key);
            $normalized[$newKey] = is_array($value)
                ? $this->normalizeArrayKeys($value)
                : $value;
        }

        return $normalized;
    }

    /**
     * @param string $key
     * @return string
     */
    private function toSnakeCase(string $key): string
    {
        $key = str_replace('-', '_', $key);

        $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);

        return strtolower($key);
    }
}
