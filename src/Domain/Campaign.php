<?php

declare(strict_types=1);

namespace App\Domain;

final class Campaign
{
    private string $id;
    private string $type;
    private CampaignStatus $status;
    private \DateTimeImmutable $scheduledAt;
    /** @var array<string, mixed> */
    private array $audienceCriteria;
    private string $editorialId;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $audienceCriteria
     */
    public function __construct(
        string $id,
        string $type,
        \DateTimeImmutable $scheduledAt,
        array $audienceCriteria,
        string $editorialId,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->status = CampaignStatus::Pending;
        $this->scheduledAt = $scheduledAt;
        $this->audienceCriteria = $audienceCriteria;
        $this->editorialId = $editorialId;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): CampaignStatus
    {
        return $this->status;
    }

    public function scheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    /** @return array<string, mixed> */
    public function audienceCriteria(): array
    {
        return $this->audienceCriteria;
    }

    public function editorialId(): string
    {
        return $this->editorialId;
    }

    public function isReadyToProcess(\DateTimeImmutable $now): bool
    {
        return $this->status === CampaignStatus::Pending && $this->scheduledAt <= $now;
    }

    public function markAsProcessing(): void
    {
        $this->status = CampaignStatus::Processing;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsSent(): void
    {
        $this->status = CampaignStatus::Sent;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsFailed(): void
    {
        $this->status = CampaignStatus::Failed;
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
