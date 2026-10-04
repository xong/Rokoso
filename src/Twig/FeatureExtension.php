<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/**
 * Areas switched off per organization in templates (see {@see Features}).
 */
final readonly class FeatureExtension
{
    public function __construct(private Features $features, private Security $security)
    {
    }

    /**
     * Whether the area (value of {@see Feature}) is available to the current user.
     */
    #[AsTwigFunction('has_feature')]
    public function hasFeature(string $feature): bool
    {
        return $this->isAvailable(Feature::from($feature));
    }

    /**
     * Whether the page of the route belongs to an area available to the current user.
     */
    #[AsTwigFunction('route_available')]
    public function routeAvailable(string $route): bool
    {
        return $this->isAvailable(Feature::forRoute($route));
    }

    private function isAvailable(?Feature $feature): bool
    {
        $user = $this->security->getUser();

        return null === $feature || !$user instanceof User || $this->features->isAvailable($user, $feature);
    }
}
