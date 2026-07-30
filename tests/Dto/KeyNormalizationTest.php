<?php

namespace Mycro\Core\Tests\Dto;

use Mycro\Core\Tests\Fixtures\UpsertMailingDto;
use PHPUnit\Framework\TestCase;

final class KeyNormalizationTest extends TestCase
{
    public function testTopLevelCamelCaseKeyMapsToSnakeCaseProperty(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'fromEmail' => 'sender@example.com',
        ]);

        $this->assertSame('sender@example.com', $dto->from_email);
        $this->assertTrue($dto->wasProvided('from_email'));
    }

    public function testTopLevelKebabCaseKeyMapsToSnakeCaseProperty(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'is-active' => true,
        ]);

        $this->assertTrue($dto->is_active);
        $this->assertTrue($dto->wasProvided('is_active'));
    }

    public function testNestedCamelCaseKeysSurviveHydration(): void
    {
        $structure = [
            'blocks' => [
                [
                    'type' => 'header',
                    'navLinks' => [
                        ['manageUrl' => 'https://example.com/manage', 'unsubText' => 'Unsubscribe'],
                    ],
                    'props' => [
                        'objectFit' => 'cover',
                        'socialIcons' => ['telegram', 'x'],
                    ],
                ],
            ],
            'footer' => [
                'copyrightText' => '© Example',
                'legalLinks' => [],
            ],
        ];

        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'structure' => $structure,
        ]);

        $this->assertSame($structure, $dto->structure);
    }

    public function testNestedListKeysAreNotReindexedOrRenamed(): void
    {
        $structure = ['Recipients' => ['A-B' => 1, 'cD' => 2]];

        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'structure' => $structure,
        ]);

        $this->assertSame(['A-B' => 1, 'cD' => 2], $dto->structure['Recipients']);
    }
}
