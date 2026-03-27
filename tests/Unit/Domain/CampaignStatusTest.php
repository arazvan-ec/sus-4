<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\CampaignStatus;
use PHPUnit\Framework\TestCase;

final class CampaignStatusTest extends TestCase
{
    public function testPendingValue(): void
    {
        self::assertSame('pending', CampaignStatus::Pending->value);
    }

    public function testProcessingValue(): void
    {
        self::assertSame('processing', CampaignStatus::Processing->value);
    }

    public function testSentValue(): void
    {
        self::assertSame('sent', CampaignStatus::Sent->value);
    }

    public function testFailedValue(): void
    {
        self::assertSame('failed', CampaignStatus::Failed->value);
    }

    public function testFromValidString(): void
    {
        self::assertSame(CampaignStatus::Pending, CampaignStatus::from('pending'));
        self::assertSame(CampaignStatus::Processing, CampaignStatus::from('processing'));
        self::assertSame(CampaignStatus::Sent, CampaignStatus::from('sent'));
        self::assertSame(CampaignStatus::Failed, CampaignStatus::from('failed'));
    }

    public function testFromInvalidStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        CampaignStatus::from('cancelled');
    }

    public function testCasesReturnsAllValues(): void
    {
        $cases = CampaignStatus::cases();
        self::assertCount(4, $cases);
    }
}
