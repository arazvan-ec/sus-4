<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Campaign;
use App\Domain\CampaignStatus;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
    public function testConstructorSetsFieldsCorrectly(): void
    {
        $scheduledAt = new \DateTimeImmutable('2026-03-28 10:00:00');
        $criteria = ['entity_type' => 'journalist', 'entity_ids' => ['123']];

        $campaign = new Campaign(
            'campaign-uuid',
            'editorial_notification',
            $scheduledAt,
            $criteria,
            'editorial-456',
        );

        self::assertSame('campaign-uuid', $campaign->id());
        self::assertSame('editorial_notification', $campaign->type());
        self::assertSame(CampaignStatus::Pending, $campaign->status());
        self::assertSame($scheduledAt, $campaign->scheduledAt());
        self::assertSame($criteria, $campaign->audienceCriteria());
        self::assertSame('editorial-456', $campaign->editorialId());
        self::assertInstanceOf(\DateTimeImmutable::class, $campaign->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $campaign->updatedAt());
    }

    public function testNewCampaignIsPending(): void
    {
        $campaign = $this->createCampaign();

        self::assertSame(CampaignStatus::Pending, $campaign->status());
    }

    public function testIsReadyToProcessWhenPendingAndScheduledTimePassed(): void
    {
        $past = new \DateTimeImmutable('-1 hour');
        $campaign = $this->createCampaign($past);
        $now = new \DateTimeImmutable();

        self::assertTrue($campaign->isReadyToProcess($now));
    }

    public function testIsNotReadyToProcessWhenScheduledInFuture(): void
    {
        $future = new \DateTimeImmutable('+1 hour');
        $campaign = $this->createCampaign($future);
        $now = new \DateTimeImmutable();

        self::assertFalse($campaign->isReadyToProcess($now));
    }

    public function testIsNotReadyToProcessWhenNotPending(): void
    {
        $past = new \DateTimeImmutable('-1 hour');
        $campaign = $this->createCampaign($past);
        $campaign->markAsProcessing();
        $now = new \DateTimeImmutable();

        self::assertFalse($campaign->isReadyToProcess($now));
    }

    public function testMarkAsProcessing(): void
    {
        $campaign = $this->createCampaign();

        $campaign->markAsProcessing();

        self::assertSame(CampaignStatus::Processing, $campaign->status());
    }

    public function testMarkAsSent(): void
    {
        $campaign = $this->createCampaign();
        $campaign->markAsProcessing();

        $campaign->markAsSent();

        self::assertSame(CampaignStatus::Sent, $campaign->status());
    }

    public function testMarkAsFailed(): void
    {
        $campaign = $this->createCampaign();
        $campaign->markAsProcessing();

        $campaign->markAsFailed();

        self::assertSame(CampaignStatus::Failed, $campaign->status());
    }

    public function testFullLifecyclePendingToSent(): void
    {
        $campaign = $this->createCampaign(new \DateTimeImmutable('-1 hour'));
        $now = new \DateTimeImmutable();

        self::assertTrue($campaign->isReadyToProcess($now));
        self::assertSame(CampaignStatus::Pending, $campaign->status());

        $campaign->markAsProcessing();
        self::assertSame(CampaignStatus::Processing, $campaign->status());
        self::assertFalse($campaign->isReadyToProcess($now));

        $campaign->markAsSent();
        self::assertSame(CampaignStatus::Sent, $campaign->status());
    }

    public function testFullLifecyclePendingToFailed(): void
    {
        $campaign = $this->createCampaign();

        $campaign->markAsProcessing();
        $campaign->markAsFailed();

        self::assertSame(CampaignStatus::Failed, $campaign->status());
    }

    private function createCampaign(?\DateTimeImmutable $scheduledAt = null): Campaign
    {
        return new Campaign(
            'test-campaign-id',
            'editorial_notification',
            $scheduledAt ?? new \DateTimeImmutable('+1 hour'),
            ['entity_type' => 'journalist', 'entity_ids' => ['123']],
            'editorial-test',
        );
    }
}
