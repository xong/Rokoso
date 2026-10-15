<?php

declare(strict_types=1);

namespace App\Facility;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Person reported for a seat (form data); becomes a contact or is matched to one by e-mail address.
 */
final class FacilityPerson
{
    #[Assert\Length(max: 80)]
    public ?string $firstName = null;

    #[Assert\Length(max: 80)]
    public ?string $lastName = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 40)]
    public ?string $phone = null;

    public ?\DateTimeImmutable $reportedOn;

    /** Delete the person who held the seat so far (if they hold no other seat) */
    public bool $removePrevious = false;

    public function __construct()
    {
        $this->reportedOn = new \DateTimeImmutable('today');
    }

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context): void
    {
        if ('' === trim(($this->firstName ?? '').($this->lastName ?? ''))) {
            $context->buildViolation('facility.person_name_required')->atPath('lastName')->addViolation();
        }
    }
}
