<?php

declare(strict_types=1);

namespace App\Participation;

use App\Calendar\CalendarService;
use App\Calendar\Occurrence;
use App\Entity\ContactGroup;
use App\Entity\PublicSettings;
use App\Entity\Resolution;
use App\Entity\Survey;
use App\Enum\Feature;
use App\Repository\ContactGroupRepository;
use App\Repository\ResolutionRepository;
use App\Repository\SurveyRepository;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Contents of the start page of the public page and its sections – the navigation links to them (#events, #contact …).
 */
final class PublicPage implements ResetInterface
{
    /** @var array<int, array{events: list<Occurrence>, surveys: list<Survey>, resolutions: list<Resolution>, groups: list<ContactGroup>}> */
    private array $contents = [];

    public function __construct(
        private readonly CalendarService $calendar,
        private readonly SurveyRepository $surveys,
        private readonly ResolutionRepository $resolutions,
        private readonly ContactGroupRepository $groups,
    ) {
    }

    /**
     * Lists shown on the start page; empty for parts that are switched off.
     *
     * @return array{events: list<Occurrence>, surveys: list<Survey>, resolutions: list<Resolution>, groups: list<ContactGroup>}
     */
    public function contents(PublicSettings $settings): array
    {
        $organization = $settings->getOrganization();
        $today = new \DateTimeImmutable('today');

        return $this->contents[spl_object_id($settings)] ??= [
            'events' => $settings->offers('events') ? \array_slice($this->calendar->publicOccurrences($organization, $today, $today->modify('+6 months')), 0, 20) : [],
            'surveys' => $settings->offers('surveys') ? $this->surveys->findListed($organization) : [],
            'resolutions' => $settings->offers('info') && $organization->hasFeature(Feature::Resolutions) ? $this->resolutions->findPublic($organization) : [],
            'groups' => $settings->offers('subscribe') ? $this->groups->findPublicSubscribe($organization) : [],
        ];
    }

    /**
     * Sections of the start page in page order: anchor => translation key of the heading.
     *
     * @return array<string, string>
     */
    #[AsTwigFunction('public_sections')]
    public function sections(PublicSettings $settings): array
    {
        $contents = $this->contents($settings);

        return array_filter([
            'events' => [] !== $contents['events'] ? 'public.home.events' : null,
            'surveys' => [] !== $contents['surveys'] ? 'public.home.surveys' : null,
            'resolutions' => [] !== $contents['resolutions'] ? 'public.home.resolutions' : null,
            'contact' => $settings->offers('contact') && null !== $settings->getContactAccount() ? 'public.contact.title' : null,
            'subscribe' => [] !== $contents['groups'] ? 'public.subscribe.nav' : null,
            'confidential' => $settings->offers('confidential') ? 'public.confidential.nav' : null,
        ]);
    }

    public function reset(): void
    {
        $this->contents = [];
    }
}
