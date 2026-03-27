<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\EntityType;
use PHPUnit\Framework\TestCase;

final class EntityTypeTest extends TestCase
{
    public function testJournalistValue(): void
    {
        self::assertSame('journalist', EntityType::Journalist->value);
    }

    public function testTagValue(): void
    {
        self::assertSame('tag', EntityType::Tag->value);
    }

    public function testSectionValue(): void
    {
        self::assertSame('section', EntityType::Section->value);
    }

    public function testFromValidString(): void
    {
        self::assertSame(EntityType::Journalist, EntityType::from('journalist'));
        self::assertSame(EntityType::Tag, EntityType::from('tag'));
        self::assertSame(EntityType::Section, EntityType::from('section'));
    }

    public function testFromInvalidStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        EntityType::from('invalid');
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        self::assertNull(EntityType::tryFrom('invalid'));
    }

    public function testCasesReturnsAllValues(): void
    {
        $cases = EntityType::cases();
        self::assertCount(3, $cases);
    }
}
