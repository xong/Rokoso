<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Feature;
use App\Repository\PublicSettingsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Public participation of an organization: page at /p/{slug}, each feature switchable, spam protection.
 */
#[ORM\Entity(repositoryClass: PublicSettingsRepository::class)]
#[UniqueEntity('slug', message: 'public.slug_taken')]
class PublicSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60, unique: true, nullable: true)]
    #[Assert\Length(min: 3, max: 60)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'public.slug_invalid')]
    private ?string $slug = null;

    /** Info page: intro and news (Markdown), selected resolutions, public events */
    #[ORM\Column]
    private bool $infoEnabled = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 20000)]
    private ?string $intro = null;

    #[ORM\Column]
    private bool $contactEnabled = false;

    /** Inbox the contact form delivers to */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?MailAccount $contactAccount = null;

    #[ORM\Column]
    private bool $topicsEnabled = false;

    #[ORM\Column]
    private bool $surveysEnabled = false;

    #[ORM\Column]
    private bool $eventsEnabled = false;

    #[ORM\Column]
    private bool $subscribeEnabled = false;

    /** Anonymous confidential contact with access code, answered by the confidants */
    #[ORM\Column(options: ['default' => false])]
    private bool $confidentialEnabled = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $privacyNotice = null;

    /** Invisible protection: honeypot, minimum time and throttling */
    #[ORM\Column]
    private bool $spamInvisible = true;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 120)]
    private int $spamMinSeconds = 3;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 1000)]
    private int $spamMaxPerHour = 10;

    /** Submissions only count after the sender clicked a link sent by email */
    #[ORM\Column]
    private bool $spamConfirmEmail = false;

    /** @var Collection<int, PublicTopic> */
    #[ORM\OneToMany(targetEntity: PublicTopic::class, mappedBy: 'settings', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    private Collection $topics;

    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
        private Organization $organization,
    ) {
        $this->topics = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->isActive() && null === $this->slug) {
            $context->buildViolation('public.slug_required')->atPath('slug')->addViolation();
        }
        if ($this->contactEnabled && null === $this->contactAccount) {
            $context->buildViolation('public.account_required')->atPath('contactAccount')->addViolation();
        }
        if (null !== $this->contactAccount && $this->contactAccount->getOrganization() !== $this->organization) {
            $context->buildViolation('public.account_required')->atPath('contactAccount')->addViolation();
        }
        if ($this->confidentialEnabled && [] === $this->organization->getConfidants()) {
            $context->buildViolation('public.confidants_required')->atPath('confidentialEnabled')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    /** At least one feature is switched on */
    public function isActive(): bool
    {
        return $this->infoEnabled || $this->contactEnabled || $this->surveysEnabled || $this->eventsEnabled || $this->subscribeEnabled || $this->confidentialEnabled;
    }

    /**
     * Whether the public page shows the part (info, contact, surveys, events, subscribe, confidential): switched on here and
     * its area used by the organization – the switches stay stored while an area is off.
     */
    public function offers(string $part): bool
    {
        [$enabled, $feature] = match ($part) {
            'info' => [$this->infoEnabled, null],
            'contact' => [$this->contactEnabled, Feature::Mail],
            'surveys' => [$this->surveysEnabled, Feature::Surveys],
            'events' => [$this->eventsEnabled, Feature::Calendar],
            'subscribe' => [$this->subscribeEnabled, Feature::Contacts],
            'confidential' => [$this->confidentialEnabled, null],
            default => throw new \InvalidArgumentException($part),
        };

        return $enabled && $this->organization->hasFeature(Feature::PublicPage) && (null === $feature || $this->organization->hasFeature($feature));
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = null === $slug || '' === trim($slug) ? null : mb_strtolower(trim($slug));

        return $this;
    }

    public function isInfoEnabled(): bool
    {
        return $this->infoEnabled;
    }

    public function setInfoEnabled(bool $infoEnabled): static
    {
        $this->infoEnabled = $infoEnabled;

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = $intro;

        return $this;
    }

    public function isContactEnabled(): bool
    {
        return $this->contactEnabled;
    }

    public function setContactEnabled(bool $contactEnabled): static
    {
        $this->contactEnabled = $contactEnabled;

        return $this;
    }

    public function getContactAccount(): ?MailAccount
    {
        return $this->contactAccount;
    }

    public function setContactAccount(?MailAccount $contactAccount): static
    {
        $this->contactAccount = $contactAccount;

        return $this;
    }

    public function isTopicsEnabled(): bool
    {
        return $this->topicsEnabled;
    }

    public function setTopicsEnabled(bool $topicsEnabled): static
    {
        $this->topicsEnabled = $topicsEnabled;

        return $this;
    }

    /**
     * Topics offered in the contact form (none when topics are switched off).
     *
     * @return list<PublicTopic>
     */
    public function getActiveTopics(): array
    {
        return $this->topicsEnabled ? array_values($this->topics->toArray()) : [];
    }

    public function isSurveysEnabled(): bool
    {
        return $this->surveysEnabled;
    }

    public function setSurveysEnabled(bool $surveysEnabled): static
    {
        $this->surveysEnabled = $surveysEnabled;

        return $this;
    }

    public function isEventsEnabled(): bool
    {
        return $this->eventsEnabled;
    }

    public function setEventsEnabled(bool $eventsEnabled): static
    {
        $this->eventsEnabled = $eventsEnabled;

        return $this;
    }

    public function isSubscribeEnabled(): bool
    {
        return $this->subscribeEnabled;
    }

    public function setSubscribeEnabled(bool $subscribeEnabled): static
    {
        $this->subscribeEnabled = $subscribeEnabled;

        return $this;
    }

    public function isConfidentialEnabled(): bool
    {
        return $this->confidentialEnabled;
    }

    public function setConfidentialEnabled(bool $confidentialEnabled): static
    {
        $this->confidentialEnabled = $confidentialEnabled;

        return $this;
    }

    public function getPrivacyNotice(): ?string
    {
        return $this->privacyNotice;
    }

    public function setPrivacyNotice(?string $privacyNotice): static
    {
        $this->privacyNotice = $privacyNotice;

        return $this;
    }

    public function isSpamInvisible(): bool
    {
        return $this->spamInvisible;
    }

    public function setSpamInvisible(bool $spamInvisible): static
    {
        $this->spamInvisible = $spamInvisible;

        return $this;
    }

    public function getSpamMinSeconds(): int
    {
        return $this->spamMinSeconds;
    }

    public function setSpamMinSeconds(?int $spamMinSeconds): static
    {
        $this->spamMinSeconds = $spamMinSeconds ?? 0;

        return $this;
    }

    public function getSpamMaxPerHour(): int
    {
        return $this->spamMaxPerHour;
    }

    public function setSpamMaxPerHour(?int $spamMaxPerHour): static
    {
        $this->spamMaxPerHour = $spamMaxPerHour ?? 10;

        return $this;
    }

    public function isSpamConfirmEmail(): bool
    {
        return $this->spamConfirmEmail;
    }

    public function setSpamConfirmEmail(bool $spamConfirmEmail): static
    {
        $this->spamConfirmEmail = $spamConfirmEmail;

        return $this;
    }

    /**
     * @return Collection<int, PublicTopic>
     */
    public function getTopics(): Collection
    {
        return $this->topics;
    }

    public function addTopic(PublicTopic $topic): static
    {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
        }

        return $this;
    }

    public function removeTopic(PublicTopic $topic): static
    {
        $this->topics->removeElement($topic);

        return $this;
    }
}
