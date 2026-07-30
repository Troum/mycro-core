<?php

namespace Mycro\Core\Tests\Dto;

use Mycro\Core\Contracts\BaseDto;
use Mycro\Core\Services\DefaultPropertyMapper;
use Mycro\Core\Tests\Fixtures\LegacyTupleMapper;
use Mycro\Core\Tests\Fixtures\UpsertMailingDto;
use PHPUnit\Framework\TestCase;

final class PartialHydrationTest extends TestCase
{
    protected function tearDown(): void
    {
        BaseDto::setPropertyMapper(new DefaultPropertyMapper());
    }

    public function testOmittedKeyWithDefaultValueIsNotProvided(): void
    {
        $dto = new UpsertMailingDto(['name' => 'Weekly digest']);

        $this->assertFalse($dto->wasProvided('subject'));
        $this->assertArrayNotHasKey('subject', $dto->providedAttributes());

        $this->assertSame('', $dto->toArray()['subject']);
    }

    public function testOmittedKeyWithFalsyDefaultIsNotProvided(): void
    {
        $dto = new UpsertMailingDto(['name' => 'Weekly digest']);

        $this->assertFalse($dto->wasProvided('is_active'));
        $this->assertFalse($dto->toArray()['is_active']);
        $this->assertSame(['name' => 'Weekly digest'], $dto->providedAttributes());
    }

    public function testOptionalMapPropertyWithoutKeyIsNotProvided(): void
    {
        $dto = new UpsertMailingDto(['name' => 'Weekly digest']);

        $this->assertFalse($dto->wasProvided('reply_to_email'));
        $this->assertNull($dto->toArray()['reply_to_email']);
    }

    public function testExplicitNullCountsAsProvided(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'reply_to' => null,
        ]);

        $this->assertTrue($dto->wasProvided('reply_to_email'));
        $this->assertArrayHasKey('reply_to_email', $dto->providedAttributes());
        $this->assertNull($dto->providedAttributes()['reply_to_email']);
    }

    public function testExplicitEmptyStringOverDefaultCountsAsProvided(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'subject' => '',
        ]);

        $this->assertTrue($dto->wasProvided('subject'));
        $this->assertSame('', $dto->providedAttributes()['subject']);
    }

    public function testAliasIsRecordedUnderPropertyName(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'reply_to' => 'reply@example.com',
        ]);

        $this->assertTrue($dto->wasProvided('reply_to_email'));
        $this->assertFalse($dto->wasProvided('reply_to'));
        $this->assertSame(
            ['name' => 'Weekly digest', 'reply_to_email' => 'reply@example.com'],
            $dto->providedAttributes()
        );
    }

    public function testProvidedAttributesHoldTransformedValue(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'from_email' => '  sender@example.com  ',
        ]);

        $this->assertTrue($dto->wasProvided('from_email'));
        $this->assertSame('sender@example.com', $dto->providedAttributes()['from_email']);
    }

    public function testProvidedAttributesIsSubsetOfToArray(): void
    {
        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'subject' => 'Hello',
        ]);

        $toArray = $dto->toArray();

        $this->assertSame(['name', 'subject'], array_keys($dto->providedAttributes()));
        $this->assertCount(6, $toArray);

        foreach ($dto->providedAttributes() as $key => $value) {
            $this->assertSame($toArray[$key], $value);
        }
    }

    public function testLegacyTwoElementTupleStillHydrates(): void
    {
        BaseDto::setPropertyMapper(new LegacyTupleMapper());

        $dto = new UpsertMailingDto([
            'name' => 'Weekly digest',
            'subject' => 'Hello',
        ]);

        $this->assertSame('Weekly digest', $dto->name);
        $this->assertSame('Hello', $dto->subject);

        $this->assertSame([], $dto->providedAttributes());
        $this->assertFalse($dto->wasProvided('name'));
    }

    public function testProvidedPropertiesAreNotExposedInToArray(): void
    {
        $dto = new UpsertMailingDto(['name' => 'Weekly digest']);

        $this->assertArrayNotHasKey('providedProperties', $dto->toArray());
    }
}
