<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\User;
use App\Entity\WikiPage;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WikiPage>
 */
class WikiPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, WikiPage::class);
    }

    /**
     * Pages of the organizations the user is a full member of, optionally filtered by text, sorted by title.
     *
     * @return list<WikiPage>
     */
    public function findVisibleFor(User $user, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join(Membership::class, 'm', 'WITH', 'm.organization = p.organization AND m.user = :viewer AND '.Membership::fullDql('m'))
            ->andWhere($this->features->dql('IDENTITY(p.organization)', Feature::Wiki))
            ->setParameter('viewer', $user)
            ->orderBy('p.title', 'ASC');
        $query = trim((string) $query);
        if ('' !== $query) {
            $qb->andWhere('p.title LIKE :q OR p.body LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }

        /* @var list<WikiPage> */
        return $qb->getQuery()->getResult();
    }
}
