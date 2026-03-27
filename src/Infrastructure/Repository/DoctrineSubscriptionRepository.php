<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\EntityType;
use App\Domain\Subscription;
use App\Domain\SubscriptionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByUserAndEntity(string $userId, EntityType $entityType, string $entityId): ?Subscription
    {
        return $this->entityManager
            ->getRepository(Subscription::class)
            ->findOneBy([
                'userId' => $userId,
                'entityType' => $entityType->value,
                'entityId' => $entityId,
            ]);
    }

    public function save(Subscription $subscription): void
    {
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }
}
