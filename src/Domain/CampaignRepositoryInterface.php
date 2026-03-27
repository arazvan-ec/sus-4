<?php

declare(strict_types=1);

namespace App\Domain;

interface CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void;

    /** @return Campaign[] */
    public function findPendingReadyToProcess(\DateTimeImmutable $now): array;
}
