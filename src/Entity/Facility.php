<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FacilityRole;
use App\Repository\FacilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Facility represented in the organization (e.g. a Kita whose board elects a representative and a deputy).
 * The people are contacts of the organization; replacing them keeps the contact.
 */
#[ORM\Entity(repositoryClass: FacilityRepository::class)]
#[ORM\Index(name: 'facility_name_idx', columns: ['organization_id', 'name'])]
class Facility
{
    /** A report older than this is shown as "to be checked" (boards elect yearly, but not on one date). */
    public const string REVIEW_AFTER = '-1 year';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    /** Number of the facility in the city's list, for matching a later import */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $number = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $carrier = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $district = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $city = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $representative = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $deputy = null;

    /** When the current people were reported (e.g. date of the e-mail) */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reportedOn = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = self::clean($number);

        return $this;
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function setCarrier(?string $carrier): static
    {
        $this->carrier = self::clean($carrier);

        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = self::clean($district);

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = self::clean($street);

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = self::clean($postalCode);

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = self::clean($city);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = self::clean($email);
        $this->email = null === $email ? null : mb_strtolower($email);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = self::clean($phone);

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = self::clean($notes);

        return $this;
    }

    public function getRepresentative(): ?Contact
    {
        return $this->representative;
    }

    public function getDeputy(): ?Contact
    {
        return $this->deputy;
    }

    public function getPerson(FacilityRole $role): ?Contact
    {
        return FacilityRole::Representative === $role ? $this->representative : $this->deputy;
    }

    /**
     * Puts the contact on the seat; a person holds one seat per facility (a deputy moving up leaves the deputy seat).
     */
    public function setPerson(FacilityRole $role, ?Contact $contact): static
    {
        if (FacilityRole::Representative === $role) {
            $this->representative = $contact;
            if (null !== $contact && $this->deputy === $contact) {
                $this->deputy = null;
            }
        } else {
            $this->deputy = $contact;
            if (null !== $contact && $this->representative === $contact) {
                $this->representative = null;
            }
        }

        return $this;
    }

    public function getReportedOn(): ?\DateTimeImmutable
    {
        return $this->reportedOn;
    }

    public function setReportedOn(?\DateTimeImmutable $reportedOn): static
    {
        $this->reportedOn = $reportedOn;

        return $this;
    }

    /** Someone is reported, but not within the last year (or without date). */
    public function needsReview(?\DateTimeImmutable $now = null): bool
    {
        if (null === $this->representative && null === $this->deputy) {
            return false;
        }

        return null === $this->reportedOn || $this->reportedOn < ($now ?? new \DateTimeImmutable('today'))->modify(self::REVIEW_AFTER);
    }

    public function getAddress(): ?string
    {
        $place = trim(($this->postalCode ?? '').' '.($this->city ?? ''));
        $address = implode(', ', array_filter([$this->street, $place]));

        return '' === $address ? null : $address;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function clean(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
