<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Facility;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facility>
 */
class FacilityRepository extends ServiceEntityRepository
{
    /** List filters */
    public const string MISSING = 'missing';
    public const string NO_DEPUTY = 'no_deputy';
    public const string REVIEW = 'review';

    /** Recipients of a circular */
    public const string TO_REPRESENTATIVES = 'representatives';
    public const string TO_ALL = 'all';
    public const string TO_MISSING = 'missing';

    public function __construct(ManagerRegistry $registry, private readonly Features $features)
    {
        parent::__construct($registry, Facility::class);
    }

    /** Facilities of the organizations the user is a full member of (with the area and contacts switched on). */
    public function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->addSelect('o', 'r', 'd')
            ->join('f.organization', 'o')
            ->join(Membership::class, 'fm', 'ON', 'fm.organization = o AND fm.user = :viewer AND '.Membership::fullDql('fm'))
            ->leftJoin('f.representative', 'r')
            ->leftJoin('f.deputy', 'd')
            ->andWhere($this->features->dql('o.id', Feature::Facilities))
            ->andWhere($this->features->dql('o.id', Feature::Contacts))
            ->setParameter('viewer', $user);
    }

    /**
     * @return list<Facility>
     */
    public function search(User $user, string $query = '', ?string $status = null, ?string $district = null): array
    {
        $qb = $this->visibleQuery($user)->orderBy('o.name')->addOrderBy('f.name');
        if ('' !== $query) {
            $qb->andWhere('f.name LIKE :q OR f.number LIKE :q OR f.carrier LIKE :q OR f.district LIKE :q OR f.street LIKE :q'
                .' OR r.firstName LIKE :q OR r.lastName LIKE :q OR r.email LIKE :q OR d.firstName LIKE :q OR d.lastName LIKE :q OR d.email LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $district && '' !== $district) {
            $qb->andWhere('f.district = :district')->setParameter('district', $district);
        }
        match ($status) {
            self::MISSING => $qb->andWhere('f.representative IS NULL'),
            self::NO_DEPUTY => $qb->andWhere('f.deputy IS NULL'),
            self::REVIEW => $qb->andWhere('f.representative IS NOT NULL OR f.deputy IS NOT NULL')
                ->andWhere('f.reportedOn IS NULL OR f.reportedOn < :reviewBefore')
                ->setParameter('reviewBefore', (new \DateTimeImmutable('today'))->modify(Facility::REVIEW_AFTER)),
            default => null,
        };

        /* @var list<Facility> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Values in use, for the district filter and the suggestions in the form.
     *
     * @param 'district'|'carrier' $field
     *
     * @return list<string>
     */
    public function distinctValues(User $user, string $field): array
    {
        $rows = $this->visibleQuery($user)
            ->select(\sprintf('DISTINCT f.%s AS value', $field))
            ->andWhere(\sprintf('f.%s IS NOT NULL', $field))
            ->orderBy('value')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($rows, is_string(...)));
    }

    /**
     * Addresses for a circular, one per person: representatives, representatives and deputies,
     * or the facilities' own address where no representative is reported.
     *
     * @param self::TO_* $recipients
     *
     * @return list<string> "Name <address>"
     */
    public function addresses(Organization $organization, string $recipients, ?string $district = null): array
    {
        if (!$organization->hasFeature(Feature::Facilities) || !$organization->hasFeature(Feature::Contacts)) {
            return [];
        }
        $qb = $this->createQueryBuilder('f')
            ->addSelect('r', 'd')
            ->leftJoin('f.representative', 'r')
            ->leftJoin('f.deputy', 'd')
            ->andWhere('f.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('f.name');
        if (null !== $district && '' !== $district) {
            $qb->andWhere('f.district = :district')->setParameter('district', $district);
        }

        $addresses = [];
        /** @var Facility $facility */
        foreach ($qb->getQuery()->getResult() as $facility) {
            if (self::TO_MISSING === $recipients) {
                if (null === $facility->getRepresentative() && null !== $facility->getEmail()) {
                    $addresses[$facility->getEmail()] ??= self::address($facility->getName(), $facility->getEmail());
                }
                continue;
            }
            $people = self::TO_ALL === $recipients ? [$facility->getRepresentative(), $facility->getDeputy()] : [$facility->getRepresentative()];
            foreach (array_filter($people) as $contact) {
                $email = $contact->getEmail() ?? $contact->getEmail2();
                if (null !== $email) {
                    $addresses[$email] ??= self::address($contact->getDisplayName(), $email);
                }
            }
        }

        return array_values($addresses);
    }

    /**
     * Facilities where the contact holds a seat.
     *
     * @return list<Facility>
     */
    public function findForContact(Contact $contact): array
    {
        /* @var list<Facility> */
        return $this->createQueryBuilder('f')
            ->andWhere('f.representative = :contact OR f.deputy = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('f.name')
            ->getQuery()
            ->getResult();
    }

    private static function address(string $name, string $email): string
    {
        $name = trim(str_replace(['"', '<', '>', ',', ';'], '', $name));

        return '' === $name ? $email : \sprintf('%s <%s>', $name, $email);
    }
}
