<?php

namespace Mycro\Core\Contracts;

interface TransformerContract
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function transform(mixed $value): mixed;
}
