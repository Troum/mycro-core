<?php

namespace Mycro\Core\Tests\Fixtures;

use Mycro\Core\Contracts\TransformerContract;

final class TrimTransformer implements TransformerContract
{
    public function transform(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
