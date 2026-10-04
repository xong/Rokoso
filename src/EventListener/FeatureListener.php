<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pages of an area are not found when none of the user's organizations uses it (see {@see Feature::routePrefixes()}),
 * or when the page belongs to an organization that switched the area off (organization or object with
 * getOrganization() among the controller arguments, e.g. mail accounts and rules in the administration).
 */
final readonly class FeatureListener
{
    public function __construct(private Features $features, private Security $security)
    {
    }

    #[AsEventListener(KernelEvents::CONTROLLER)]
    public function onController(ControllerEvent $event): void
    {
        $feature = Feature::forRoute((string) $event->getRequest()->attributes->get('_route'));
        $user = $this->security->getUser();
        if (null !== $feature && $user instanceof User && !$this->features->isAvailable($user, $feature)) {
            throw new NotFoundHttpException();
        }
    }

    /** Before #[IsGranted] (priority 20): a switched-off area is not found rather than forbidden */
    #[AsEventListener(KernelEvents::CONTROLLER_ARGUMENTS, priority: 25)]
    public function onArguments(ControllerArgumentsEvent $event): void
    {
        $feature = Feature::forRoute((string) $event->getRequest()->attributes->get('_route'));
        if (null === $feature) {
            return;
        }
        foreach ($event->getArguments() as $argument) {
            $organization = match (true) {
                $argument instanceof Organization => $argument,
                \is_object($argument) && method_exists($argument, 'getOrganization') => $argument->getOrganization(),
                default => null,
            };
            if ($organization instanceof Organization && !$organization->hasFeature($feature)) {
                throw new NotFoundHttpException();
            }
        }
    }
}
