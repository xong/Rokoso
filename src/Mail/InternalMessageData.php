<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Internal message to individual people (group discussions belong in the forum).
 */
final class InternalMessageData
{
    /** @var list<User> */
    #[Assert\Count(min: 1, minMessage: 'internal.no_recipient')]
    public array $recipients = [];

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $subject = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 200000)]
    public string $body = '';

    public ?Message $original = null;
}
