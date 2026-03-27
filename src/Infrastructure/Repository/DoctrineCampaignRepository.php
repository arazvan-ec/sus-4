<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Campaign $campaign): void
    {
        $this->entityManager->persist($campaign);
        $this->entityManager->flush();
    }

    /** @return Campaign[] */
    public function findPendingReadyToProcess(\DateTimeImmutable $now): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(Campaign::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.scheduledAt <= :now')
            ->setParameter('status', CampaignStatus::Pending->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
