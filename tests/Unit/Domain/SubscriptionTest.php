<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\EntityType;
use App\Domain\Subscription;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testConstructorSetsFieldsCorrectly(): void
    {
        $subscription = new Subscription(
            'uuid-123',
            'user-456',
            'test@example.com',
            EntityType::Journalist,
            'journalist-789',
        );

        self::assertSame('uuid-123', $subscription->id());
        self::assertSame('user-456', $subscription->userId());
        self::assertSame('test@example.com', $subscription->email());
        self::assertSame(EntityType::Journalist, $subscription->entityType());
        self::assertSame('journalist-789', $subscription->entityId());
        self::assertSame('active', $subscription->status());
        self::assertTrue($subscription->isActive());
        self::assertInstanceOf(\DateTimeImmutable::class, $subscription->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $subscription->updatedAt());
    }

    public function testNewSubscriptionIsActive(): void
    {
        $subscription = $this->createSubscription();

        self::assertTrue($subscription->isActive());
        self::assertSame('active', $subscription->status());
    }

    public function testDeactivateSetsStatusInactive(): void
    {
        $subscription = $this->createSubscription();
        $updatedBefore = $subscription->updatedAt();

        $subscription->deactivate();

        self::assertFalse($subscription->isActive());
        self::assertSame('inactive', $subscription->status());
        self::assertGreaterThanOrEqual($updatedBefore, $subscription->updatedAt());
    }

    public function testReactivateSetsStatusActive(): void
    {
        $subscription = $this->createSubscription();
        $subscription->deactivate();

        self::assertFalse($subscription->isActive());

        $subscription->reactivate();

        self::assertTrue($subscription->isActive());
        self::assertSame('active', $subscription->status());
    }

    public function testDeactivateAndReactivateCycle(): void
    {
        $subscription = $this->createSubscription();

        self::assertTrue($subscription->isActive());

        $subscription->deactivate();
        self::assertFalse($subscription->isActive());

        $subscription->reactivate();
        self::assertTrue($subscription->isActive());

        $subscription->deactivate();
        self::assertFalse($subscription->isActive());
    }

    public function testDifferentEntityTypes(): void
    {
        foreach (EntityType::cases() as $type) {
            $subscription = new Subscription(
                'id-' . $type->value,
                'user-1',
                'user@example.com',
                $type,
                'entity-1',
            );

            self::assertSame($type, $subscription->entityType());
        }
    }

    private function createSubscription(): Subscription
    {
        return new Subscription(
            'uuid-test',
            'user-test',
            'test@example.com',
            EntityType::Tag,
            'tag-123',
        );
    }
}
