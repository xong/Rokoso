<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\LegalDocument;
use App\Repository\LegalTextRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class LegalExtension
{
    public function __construct(private LegalTextRepository $texts)
    {
    }

    /**
     * Privacy policy and imprint, if written – for the footer of the login pages, the profile and the system mails.
     *
     * @return list<LegalDocument>
     */
    #[AsTwigFunction('legal_documents')]
    public function documents(): array
    {
        return $this->texts->published();
    }
}
