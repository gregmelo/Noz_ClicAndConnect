<?php

namespace App\Repository;

use App\Entity\GlobalStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlobalStat>
 *
 * @method GlobalStat|null find($id, $lockMode = null, $lockVersion = null)
 * @method GlobalStat|null findOneBy(array $criteria, array $orderBy = null)
 * @method GlobalStat[]    findAll()
 * @method GlobalStat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GlobalStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GlobalStat::class);
    }

    public function getOrCreate(): GlobalStat
    {
        $stat = $this->find(1);
        if (!$stat) {
            $stat = new GlobalStat();
            $this->getEntityManager()->persist($stat);
            $this->getEntityManager()->flush();
        }
        return $stat;
    }
}
