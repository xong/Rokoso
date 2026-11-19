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

    /** Welcome text of the installation for the login page (Markdown), empty if not written */
    #[AsTwigFunction('welcome_text')]
    public function welcome(): string
    {
        return $this->texts->get(LegalDocument::Welcome)->getContent();
    }
}
