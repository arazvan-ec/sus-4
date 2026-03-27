<?php

declare(strict_types=1);

namespace App\Domain;

final class Subscription
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_INACTIVE = 'inactive';

    private string $id;
    private string $userId;
    private string $email;
    private EntityType $entityType;
    private string $entityId;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        string $email,
        EntityType $entityType,
        string $entityId,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->email = $email;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->status = self::STATUS_ACTIVE;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function entityType(): EntityType
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_INACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reactivate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
