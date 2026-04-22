<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[] Returns an array of User objects
     */
    public function search(?string $query, ?string $role): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($query) {
            $qb->andWhere('u.email LIKE :query OR u.firstName LIKE :query OR u.lastName LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($role) {
            if ($role === 'ROLE_CLIENT') {
                $qb->andWhere('u.roles LIKE :role OR u.roles LIKE :empty_json')
                   ->setParameter('role', '%"' . $role . '"%')
                   ->setParameter('empty_json', '[]');
            } else {
                $qb->andWhere('u.roles LIKE :role')
                   ->setParameter('role', '%"' . $role . '"%');
            }
        }

        return $qb->orderBy('u.email', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * @return User[]
     */
    public function findEmployees(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :ROLE_WARRIOR_JUNIOR OR u.roles LIKE :ROLE_WARRIOR OR u.roles LIKE :ROLE_SUPER_WARRIOR')
            ->setParameter('ROLE_WARRIOR_JUNIOR', '%"ROLE_WARRIOR_JUNIOR"%')
            ->setParameter('ROLE_WARRIOR', '%"ROLE_WARRIOR"%')
            ->setParameter('ROLE_SUPER_WARRIOR', '%"ROLE_SUPER_WARRIOR"%')
            ->getQuery()
            ->getResult();
    }
}
