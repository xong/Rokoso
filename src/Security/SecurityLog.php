<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\SecurityEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes the security log. The entry is persisted immediately (own flush), so it survives
 * even if the surrounding action fails afterwards.
 */
final readonly class SecurityLog
{
    public function __construct(private EntityManagerInterface $em, private RequestStack $requests)
    {
    }

    public function record(string $type, ?User $user, ?string $detail = null, ?User $actor = null): void
    {
        $ip = $this->requests->getMainRequest()?->getClientIp();
        $event = new SecurityEvent(
            $type,
            null !== $user?->getId() ? $user : null,
            $actor === $user || null === $actor?->getId() ? null : $actor,
            $detail,
            null !== $ip ? IpUtils::anonymize($ip) : null,
        );
        $this->em->persist($event);
        $this->em->flush();
    }
}
