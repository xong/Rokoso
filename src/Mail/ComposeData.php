<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Project;
use App\Entity\ShelfItem;
use App\Entity\StoredFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Form data of a new email (also reply/forward).
 */
final class ComposeData
{
    #[Assert\NotNull]
    public ?MailAccount $account = null;

    #[Assert\NotBlank]
    public string $to = '';

    public string $cc = '';

    public string $bcc = '';

    #[Assert\Length(max: 500)]
    public string $subject = '';

    #[Assert\Length(max: 200000)]
    public string $body = '';

    public ?Project $project = null;

    /** @var list<ShelfItem> files from the personal shelf to attach */
    public array $shelfItems = [];

    /** @var list<StoredFile> files from the shared file area to attach */
    public array $storedFiles = [];

    /** Keep the attachments of the forwarded message. */
    public bool $keepAttachments = true;

    /** Message this one replies to or forwards. */
    public ?Message $original = null;

    public bool $forward = false;

    /** Circular letter: one separate email per "to" address, nobody sees the others. */
    public bool $circular = false;

    /**
     * Splits "a@b.de, Name <c@d.de>; e@f.de" into single addresses.
     *
     * @return list<string>
     */
    public static function splitAddresses(string $value): array
    {
        $parts = preg_split('/[,;\n]+(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $p): bool => '' !== $p));
    }

    #[Assert\Callback]
    public function validateAddresses(ExecutionContextInterface $context): void
    {
        foreach (['to', 'cc', 'bcc'] as $field) {
            foreach (self::splitAddresses($this->{$field}) as $address) {
                $email = preg_match('/<([^>]+)>\s*$/', $address, $m) ? $m[1] : $address;
                if (false === filter_var(trim($email), \FILTER_VALIDATE_EMAIL)) {
                    $context->buildViolation('compose.invalid_address')
                        ->setParameter('%address%', $address)
                        ->atPath($field)
                        ->addViolation();
                }
            }
        }
        if ($this->circular && ('' !== trim($this->cc) || '' !== trim($this->bcc))) {
            $context->buildViolation('compose.circular_only_to')->atPath('cc')->addViolation();
        }
    }
}
