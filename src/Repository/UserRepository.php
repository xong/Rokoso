<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Platform admin: accounts by name or e-mail, deleted accounts excluded.
     *
     * @return list<User>
     */
    public function search(string $query, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NULL')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults($limit);
        if ('' !== $query = trim($query)) {
            $qb->andWhere('u.name LIKE :q OR u.email LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_').'%');
        }

        /* @var list<User> */
        return $qb->getQuery()->getResult();
    }
}
