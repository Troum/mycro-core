<?php

namespace Mycro\Core\Tests\Fixtures;

use Mycro\Core\Attributes\DefaultValue;
use Mycro\Core\Attributes\MapProperty;
use Mycro\Core\Attributes\Transform;
use Mycro\Core\Contracts\BaseDto;

final class UpsertMailingDto extends BaseDto
{
    public readonly string $name;

    #[DefaultValue('')]
    public readonly string $subject;

    #[DefaultValue(false)]
    public readonly bool $is_active;

    #[MapProperty(from: 'reply_to', required: false)]
    public readonly ?string $reply_to_email;

    #[Transform(TrimTransformer::class)]
    #[DefaultValue(null)]
    public readonly ?string $from_email;

    #[DefaultValue([])]
    public readonly array $structure;
}
