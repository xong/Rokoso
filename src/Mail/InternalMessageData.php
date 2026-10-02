<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Internal message to users, an organization or a project.
 */
final class InternalMessageData
{
    /** @var list<User> */
    public array $recipients = [];

    public ?Organization $organization = null;

    public ?Project $project = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $subject = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 200000)]
    public string $body = '';

    public ?Message $original = null;

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if ([] === $this->recipients && null === $this->organization && null === $this->project) {
            $context->buildViolation('internal.no_recipient')->atPath('recipients')->addViolation();
        }
    }
}
