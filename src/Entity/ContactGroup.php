<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Named distribution list of an organization, e.g. "Schulleitungen" or "Elternbeiräte Grundschulen".
 */
#[ORM\Entity(repositoryClass: ContactGroupRepository::class)]
class ContactGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    /** @var Collection<int, Contact> */
    #[ORM\ManyToMany(targetEntity: Contact::class, inversedBy: 'groups')]
    #[ORM\JoinTable(name: 'contact_group_member')]
    #[ORM\OrderBy(['lastName' => 'ASC', 'company' => 'ASC', 'firstName' => 'ASC'])]
    private Collection $contacts;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
        $this->contacts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    /** @return Collection<int, Contact> */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        $this->contacts->removeElement($contact);

        return $this;
    }

    /**
     * One address per member (the first one), as "Name <address>".
     *
     * @return list<string>
     */
    public function getAddresses(): array
    {
        $addresses = [];
        foreach ($this->contacts as $contact) {
            $email = $contact->getEmail() ?? $contact->getEmail2();
            if (null !== $email) {
                $name = trim(str_replace(['"', '<', '>', ',', ';'], '', $contact->getDisplayName()));
                $addresses[$email] = '' === $name ? $email : \sprintf('%s <%s>', $name, $email);
            }
        }

        return array_values($addresses);
    }

    #[Assert\Callback]
    public function validateContacts(ExecutionContextInterface $context): void
    {
        foreach ($this->contacts as $contact) {
            if ($contact->getOrganization() !== $this->organization) {
                $context->buildViolation('contact_group.wrong_organization')->atPath('contacts')->addViolation();

                return;
            }
        }
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
