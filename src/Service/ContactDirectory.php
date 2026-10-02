<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\User;
use App\Repository\ContactRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Looks up contacts by email address (loaded once per request).
 */
final class ContactDirectory implements ResetInterface
{
    /** @var array<string, Contact>|null */
    private ?array $byEmail = null;

    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly Security $security,
    ) {
    }

    public function find(string $email): ?Contact
    {
        if (null === $this->byEmail) {
            $this->byEmail = [];
            $user = $this->security->getUser();
            if ($user instanceof User) {
                foreach ($this->contacts->findWithEmail($user) as $contact) {
                    foreach ([$contact->getEmail(), $contact->getEmail2()] as $address) {
                        if (null !== $address) {
                            $this->byEmail[$address] ??= $contact;
                        }
                    }
                }
            }
        }

        return $this->byEmail[mb_strtolower(trim($email))] ?? null;
    }

    public function reset(): void
    {
        $this->byEmail = null;
    }
}
