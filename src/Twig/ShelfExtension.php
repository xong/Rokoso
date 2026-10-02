<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Attachment;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Service\Shelf;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final readonly class ShelfExtension
{
    public function __construct(private Shelf $shelf, private Security $security)
    {
    }

    #[AsTwigFunction('in_shelf')]
    public function inShelf(StoredFile|Attachment $target): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && null !== $this->shelf->find($user, $target);
    }
}
