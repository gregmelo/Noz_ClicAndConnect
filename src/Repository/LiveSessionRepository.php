<?php

namespace App\Repository;

use App\Entity\LiveSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LiveSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveSession::class);
    }

    public function findLastSessions(int $limit = 10): array
    {
        return $this->createQueryBuilder('ls')
            ->orderBy('ls.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findCurrentSession(): ?LiveSession
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('ls')
            ->where('ls.startedAt <= :now')
            ->andWhere('ls.endedAt >= :now')
            ->setParameter('now', $now)
            ->orderBy('ls.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}