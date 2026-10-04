<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\PublicSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicSettings>
 */
class PublicSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicSettings::class);
    }

    /** Settings of the organization, created on first access (not persisted) */
    public function forOrganization(Organization $organization): PublicSettings
    {
        return $this->findOneBy(['organization' => $organization]) ?? new PublicSettings($organization);
    }

    public function findActiveBySlug(string $slug): ?PublicSettings
    {
        $settings = $this->findOneBy(['slug' => $slug]);

        return null !== $settings && $settings->isActive() ? $settings : null;
    }
}
