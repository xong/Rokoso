<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;
use App\Entity\Signature;
use App\Entity\TextSnippet;
use App\Entity\User;
use App\Repository\ContactGroupRepository;
use App\Repository\ContactRepository;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Helpers while writing: recipient suggestions, signatures and text snippets.
 */
final readonly class ComposeAssistant
{
    public function __construct(
        private ContactRepository $contacts,
        private ContactGroupRepository $groups,
        private TranslatorInterface $translator,
        private OrganizationRepository $organizations,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Suggestions for the address fields: contacts and members of the own organizations, as "Name <address>",
     * then contact groups (choosing one inserts all addresses).
     *
     * @return list<string|array{label: string, value: string}>
     */
    public function recipients(User $user): array
    {
        $options = [];
        foreach ($this->contacts->findWithEmail($user) as $contact) {
            foreach ([$contact->getEmail(), $contact->getEmail2()] as $address) {
                if (null !== $address) {
                    $options[$address] ??= self::format($contact->getDisplayName(), $address);
                }
            }
        }
        foreach ($this->organizations->findForUser($user) as $organization) {
            foreach ($organization->getMembers() as $member) {
                if ($member !== $user) {
                    $options[$member->getEmail()] ??= self::format($member->getName(), $member->getEmail());
                }
            }
        }
        $options = array_values($options);
        sort($options, \SORT_NATURAL | \SORT_FLAG_CASE);
        foreach ($this->groups->findForUser($user) as $group) {
            $addresses = $group->getAddresses();
            if ([] !== $addresses) {
                $options[] = [
                    'label' => $this->translator->trans('contact_group.suggestion', ['%name%' => $group->getName(), '%count%' => \count($addresses)]),
                    'value' => implode(', ', $addresses),
                ];
            }
        }

        return $options;
    }

    /**
     * @param list<MailAccount> $accounts
     *
     * @return array<int, string> signature per account id (empty = none)
     */
    public function signatures(User $user, array $accounts): array
    {
        $byAccount = [];
        foreach ($this->em->getRepository(Signature::class)->findBy(['user' => $user]) as $signature) {
            $byAccount[spl_object_id($signature->getAccount())] = $signature->getBody();
        }
        $result = [];
        foreach ($accounts as $account) {
            $id = $account->getId();
            if (null !== $id) {
                $result[$id] = $byAccount[spl_object_id($account)] ?? '';
            }
        }

        return $result;
    }

    public function signature(User $user, MailAccount $account): ?Signature
    {
        return $this->em->getRepository(Signature::class)->findOneBy(['user' => $user, 'account' => $account]);
    }

    /**
     * Puts the signature (with the usual "-- " separator) at the top, above a quoted message.
     */
    public static function withSignature(string $body, string $signature): string
    {
        if ('' === $signature) {
            return $body;
        }
        $rest = ltrim($body, "\n");

        return "\n\n-- \n".$signature.('' === $rest ? "\n" : "\n\n".$rest);
    }

    /**
     * Text snippets of all organizations the user is a full member of.
     *
     * @return list<TextSnippet>
     */
    public function snippets(User $user): array
    {
        $organizations = $this->organizations->findForUser($user);
        if ([] === $organizations) {
            return [];
        }

        /* @var list<TextSnippet> */
        return $this->em->createQueryBuilder()
            ->select('s', 'o')
            ->from(TextSnippet::class, 's')
            ->join('s.organization', 'o')
            ->andWhere('s.organization IN (:orgs)')
            ->setParameter('orgs', $organizations)
            ->orderBy('o.name')
            ->addOrderBy('s.title')
            ->getQuery()
            ->getResult();
    }

    private static function format(string $name, string $address): string
    {
        $name = trim(str_replace(['"', '<', '>', ',', ';'], '', $name));

        return '' === $name ? $address : \sprintf('%s <%s>', $name, $address);
    }
}
