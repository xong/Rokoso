<?php

declare(strict_types=1);

namespace App\Service;

use App\Agent\AgentContext;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Areas switched off per organization ({@see Organization::hasFeature()}).
 *
 * - Menu and pages: an area is available while at least one organization of the user uses it ({@see isAvailable()}).
 * - Lists and queries: content of organizations that switched the area off is left out ({@see dql()}).
 * - Single objects: voters deny access via {@see Organization::hasFeature()}.
 */
final class Features implements ResetInterface
{
    /** @var array<string, list<int>>|null feature value => organization ids */
    private ?array $disabled = null;

    /** @var array<int, list<Organization>> user id => organizations */
    private array $organizations = [];

    public function __construct(private readonly EntityManagerInterface $em, private readonly AgentContext $agent)
    {
    }

    /**
     * Whether the area appears for the user: one of their organizations (also as guest) uses it,
     * or they belong to no organization yet (personal entries).
     */
    public function isAvailable(User $user, Feature $feature): bool
    {
        $organizations = $this->organizationsOf($user);
        if ([] === $organizations) {
            return true;
        }
        foreach ($organizations as $organization) {
            if ($organization->hasFeature($feature) && $this->agent->allows($organization)) {
                return true;
            }
        }

        return false;
    }

    /**
     * DQL condition for content that is not tied to an area (e.g. internal messages): while an AI agent works,
     * organizations that do not allow agents are left out.
     */
    public function agentDql(string $organizationId): string
    {
        return $this->agent->isActive() ? $this->dql($organizationId, Feature::Agents) : '1 = 1';
    }

    /**
     * DQL condition: the organization (id expression, e.g. "IDENTITY(i.organization)" or "o.id") has the area switched on;
     * entries without organization always pass. Ids are inlined (integers only), so no parameter is needed.
     */
    public function dql(string $organizationId, Feature $feature): string
    {
        $ids = $this->disabledOrganizationIds($feature);

        return [] === $ids ? '1 = 1' : \sprintf('(%1$s IS NULL OR %1$s NOT IN (%2$s))', $organizationId, implode(', ', $ids));
    }

    /**
     * @return list<int>
     */
    public function disabledOrganizationIds(Feature $feature): array
    {
        if (null === $this->disabled) {
            $this->disabled = [];
            /** @var list<array{id: int, disabledFeatures: list<string>}> $rows */
            $rows = $this->em->createQueryBuilder()
                ->select('o.id, o.disabledFeatures')
                ->from(Organization::class, 'o')
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                foreach ($row['disabledFeatures'] as $value) {
                    $this->disabled[$value][] = $row['id'];
                }
            }
        }

        $ids = $this->disabled[$feature->value] ?? [];
        if (Feature::Agents !== $feature && $this->agent->isActive()) {
            $ids = array_values(array_unique([...$ids, ...($this->disabled[Feature::Agents->value] ?? [])]));
        }

        return $ids;
    }

    public function reset(): void
    {
        $this->disabled = null;
        $this->organizations = [];
    }

    /**
     * Organizations with an active membership (full or guest).
     *
     * @return list<Organization>
     */
    private function organizationsOf(User $user): array
    {
        $key = $user->getId() ?? 0;
        if (!isset($this->organizations[$key])) {
            /** @var list<Membership> $memberships */
            $memberships = $this->em->getRepository(Membership::class)->findBy(['user' => $user]);
            $this->organizations[$key] = array_values(array_map(static fn (Membership $m): Organization => $m->getOrganization(),
                array_filter($memberships, static fn (Membership $m): bool => $m->isActive())));
        }

        return $this->organizations[$key];
    }
}
