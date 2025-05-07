<?php

namespace Mycro\Core\Exceptions;

use Exception;

class DtoHydrationException extends Exception
{
    public function __construct(string $property, string $class)
    {
        parent::__construct("Не передано обязательное свойство '{$property}' при создании DTO класса {$class}");
    }
}
