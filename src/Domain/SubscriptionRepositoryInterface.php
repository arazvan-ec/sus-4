<?php

declare(strict_types=1);

namespace App\Domain;

interface SubscriptionRepositoryInterface
{
    public function findByUserAndEntity(string $userId, EntityType $entityType, string $entityId): ?Subscription;

    public function save(Subscription $subscription): void;
}
