<?php

namespace Mycro\Core\Exceptions;

use Exception;

class ReadonlyPropertyUpdateException extends Exception
{
    public function __construct(string $property, string $class)
    {
        parent::__construct("Невозможно переопределить readonly-свойство '{$property}' в классе {$class}");
    }
}
