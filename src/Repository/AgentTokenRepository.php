<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AgentToken;
use App\Entity\OAuthClient;
use App\Entity\OAuthCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentToken>
 */
class AgentTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentToken::class);
    }

    public function findValid(string $secret): ?AgentToken
    {
        if (!str_starts_with($secret, AgentToken::PERSONAL_PREFIX) && !str_starts_with($secret, AgentToken::ACCESS_PREFIX)) {
            return null;
        }
        $token = $this->findOneBy(['tokenHash' => AgentToken::hash($secret)]);

        return null !== $token && $token->isValid() ? $token : null;
    }

    public function findByRefreshToken(string $secret): ?AgentToken
    {
        return str_starts_with($secret, AgentToken::REFRESH_PREFIX) ? $this->findOneBy(['refreshHash' => AgentToken::hash($secret)]) : null;
    }

    /**
     * Tokens and connections of a person that still work (newest first).
     *
     * @return list<AgentToken>
     */
    public function findActiveFor(User $user): array
    {
        $tokens = $this->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return array_values(array_filter($tokens, static fn (AgentToken $t): bool => $t->isPersonal() ? $t->isValid() : $t->canRefresh() || $t->isValid()));
    }

    /**
     * Removes access that can no longer be used (expired, or ended by logging out everywhere), expired
     * authorization codes and registered apps that never connected within a week.
     */
    public function deleteUnusable(\DateTimeImmutable $now): int
    {
        $em = $this->getEntityManager();
        $stale = $em->createQueryBuilder()->select('t.id')->from(AgentToken::class, 't')->join('t.user', 'u')
            ->where('t.sessionStamp <> u.sessionStamp')
            ->orWhere('t.client IS NULL AND t.expiresAt < :now')
            ->orWhere('t.client IS NOT NULL AND (t.refreshExpiresAt IS NULL OR t.refreshExpiresAt < :now) AND t.expiresAt < :now')
            ->setParameter('now', $now)->getQuery()->getSingleColumnResult();
        $count = [] === $stale ? 0 : (int) $em->createQueryBuilder()->delete(AgentToken::class, 't')
            ->where('t.id IN (:ids)')->setParameter('ids', $stale)->getQuery()->execute();

        $em->createQueryBuilder()->delete(OAuthCode::class, 'c')->where('c.expiresAt < :now')
            ->setParameter('now', $now)->getQuery()->execute();
        $used = $em->createQueryBuilder()->select('IDENTITY(t2.client)')->from(AgentToken::class, 't2')->where('t2.client IS NOT NULL');
        $pending = $em->createQueryBuilder()->select('IDENTITY(c2.client)')->from(OAuthCode::class, 'c2');
        $em->createQueryBuilder()->delete(OAuthClient::class, 'a')
            ->where('a.createdAt < :before')
            ->andWhere('a.id NOT IN ('.$used->getDQL().')')
            ->andWhere('a.id NOT IN ('.$pending->getDQL().')')
            ->setParameter('before', $now->modify('-7 days'))->getQuery()->execute();

        return $count;
    }
}
