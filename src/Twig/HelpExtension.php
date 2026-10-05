<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Help\HelpArticle;
use App\Help\HelpCenter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class HelpExtension
{
    public function __construct(
        private HelpCenter $help,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    /**
     * Help article for the current route (target of the "?" link in the column header), null on help pages.
     */
    #[AsTwigFunction('help_article')]
    public function article(): ?HelpArticle
    {
        $route = (string) $this->requestStack->getMainRequest()?->attributes->get('_route');
        $user = $this->security->getUser();
        if ('' === $route || str_starts_with($route, 'help_') || !$user instanceof User) {
            return null;
        }

        return $this->help->forRoute($route, $user);
    }

    /**
     * All articles the user can use (entries of the command palette).
     *
     * @return list<HelpArticle>
     */
    #[AsTwigFunction('help_articles')]
    public function articles(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? array_values($this->help->availableTo($user)) : [];
    }
}
