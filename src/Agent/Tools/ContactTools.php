<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScope;
use App\Agent\Mcp\Tool;
use App\Agent\Mcp\ToolArguments;
use App\Agent\Mcp\ToolError;
use App\Agent\Mcp\ToolProvider;
use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\Facility;
use App\Entity\User;
use App\Enum\FacilityRole;
use App\Enum\Feature;
use App\Facility\FacilityPerson;
use App\Facility\FacilitySeats;
use App\Repository\ContactGroupRepository;
use App\Repository\ContactRepository;
use App\Repository\FacilityRepository;
use App\Security\Voter\ContactVoter;
use App\Security\Voter\FacilityVoter;

/**
 * Contacts with their distribution lists, and facilities with representative and deputy.
 */
final readonly class ContactTools implements ToolProvider
{
    /** argument => setter of {@see Contact} */
    private const array CONTACT_FIELDS = [
        'salutation' => 'setSalutation',
        'first_name' => 'setFirstName',
        'last_name' => 'setLastName',
        'company' => 'setCompany',
        'position' => 'setPosition',
        'email' => 'setEmail',
        'email2' => 'setEmail2',
        'phone' => 'setPhone',
        'mobile' => 'setMobile',
        'street' => 'setStreet',
        'postal_code' => 'setPostalCode',
        'city' => 'setCity',
        'website' => 'setWebsite',
        'tags' => 'setTags',
        'notes' => 'setNotes',
    ];

    /** argument => setter of {@see Facility} */
    private const array FACILITY_FIELDS = [
        'name' => 'setName',
        'number' => 'setNumber',
        'carrier' => 'setCarrier',
        'district' => 'setDistrict',
        'street' => 'setStreet',
        'postal_code' => 'setPostalCode',
        'city' => 'setCity',
        'email' => 'setEmail',
        'phone' => 'setPhone',
        'notes' => 'setNotes',
    ];

    private const array STATUS = [FacilityRepository::MISSING, FacilityRepository::NO_DEPUTY, FacilityRepository::REVIEW];

    private const int LIST_LIMIT = 200;

    public function __construct(
        private ToolKit $kit,
        private AgentContext $agent,
        private ContactRepository $contacts,
        private ContactGroupRepository $groups,
        private FacilityRepository $facilities,
        private FacilitySeats $seats,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search_contacts', 'Searches contacts by name, company, e-mail, position, tags or city (empty query = all, up to 200).',
        scope: AgentScope::ContactsRead,
        properties: [
            'query' => ['type' => 'string'],
            'group_id' => ['type' => 'integer', 'description' => 'Only members of this distribution list (list_contact_groups)'],
        ])]
    public function searchContacts(ToolArguments $args, User $user): array
    {
        $group = null;
        if ($args->has('group_id')) {
            $group = $this->group($user, $args->requiredInt('group_id'));
        }
        $found = array_values(array_filter($this->contacts->search($user, $args->string('query'), $group), fn (Contact $c): bool => $this->agent->allows($c->getOrganization())));

        return [
            'contacts' => array_map($this->contactSummary(...), \array_slice($found, 0, self::LIST_LIMIT)),
            'total' => \count($found),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('get_contact', 'All details of a contact, with distribution lists and facility seats.',
        scope: AgentScope::ContactsRead,
        properties: ['contact_id' => ['type' => 'integer']],
        required: ['contact_id'])]
    public function getContact(ToolArguments $args, User $user): array
    {
        return $this->contactDetails($this->contact($args));
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('list_contact_groups', 'Distribution lists (contact groups) per organization.', scope: AgentScope::ContactsRead)]
    public function listContactGroups(ToolArguments $args, User $user): array
    {
        return ['groups' => array_map(static fn (ContactGroup $g): array => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'description' => $g->getDescription(),
            'organization' => ToolKit::org($g->getOrganization()),
            'contacts' => $g->getContacts()->count(),
        ], $this->visibleGroups($user))];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_contact', 'Creates a contact. Search first (search_contacts) to avoid duplicates. A name or a company is required.',
        scope: AgentScope::ContactsWrite,
        properties: self::CONTACT_PROPERTIES + ['organization_id' => ['type' => 'integer', 'description' => 'Needed when the person belongs to several organizations']],
        readOnly: false)]
    public function createContact(ToolArguments $args, User $user): array
    {
        $contact = (new Contact($user))->setOrganization($this->kit->organization($user, Feature::Contacts, $args->int('organization_id'), personal: true));
        $this->fillContact($contact, $args, $user);
        $this->kit->validate($contact);
        $this->kit->em->persist($contact);
        $this->kit->em->flush();

        return $this->contactDetails($contact);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('update_contact', 'Changes a contact: only the given fields; an empty string clears a field. group_ids replaces the distribution lists.',
        scope: AgentScope::ContactsWrite,
        properties: self::CONTACT_PROPERTIES + ['contact_id' => ['type' => 'integer']],
        required: ['contact_id'],
        readOnly: false,
        idempotent: true)]
    public function updateContact(ToolArguments $args, User $user): array
    {
        $contact = $this->contact($args);
        $this->fillContact($contact, $args, $user);
        $this->kit->validate($contact);
        $this->kit->em->flush();

        return $this->contactDetails($contact);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('search_facilities', 'Searches facilities (schools, Kitas …) by name, number, carrier, district, street or the people on their seats.',
        scope: AgentScope::ContactsRead,
        properties: [
            'query' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => self::STATUS, 'description' => 'missing = no representative, no_deputy, review = report is old'],
            'district' => ['type' => 'string'],
        ])]
    public function searchFacilities(ToolArguments $args, User $user): array
    {
        $found = array_values(array_filter(
            $this->facilities->search($user, $args->string('query'), $args->choice('status', self::STATUS), $args->optionalString('district')),
            fn (Facility $f): bool => $this->agent->allows($f->getOrganization()),
        ));

        return [
            'facilities' => array_map($this->facilityDetails(...), \array_slice($found, 0, self::LIST_LIMIT)),
            'total' => \count($found),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('get_facility', 'A facility with address, representative and deputy.',
        scope: AgentScope::ContactsRead,
        properties: ['facility_id' => ['type' => 'integer']],
        required: ['facility_id'])]
    public function getFacility(ToolArguments $args, User $user): array
    {
        return $this->facilityDetails($this->facility($args));
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('create_facility', 'Creates a facility. Search first (search_facilities) to avoid duplicates. People are set with set_facility_person.',
        scope: AgentScope::ContactsWrite,
        properties: self::FACILITY_PROPERTIES + ['organization_id' => ['type' => 'integer']],
        required: ['name'],
        readOnly: false)]
    public function createFacility(ToolArguments $args, User $user): array
    {
        $organization = $this->kit->organization($user, Feature::Facilities, $args->int('organization_id'));
        if (null === $organization || !$organization->hasFeature(Feature::Contacts)) {
            throw new ToolError('Facilities need the contacts area of the organization.');
        }
        $facility = new Facility($organization);
        $this->fillFacility($facility, $args);
        $this->kit->check(FacilityVoter::EDIT, $facility, $organization);
        $this->kit->validate($facility);
        $this->kit->em->persist($facility);
        $this->kit->em->flush();

        return $this->facilityDetails($facility);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('update_facility', 'Changes a facility: only the given fields; an empty string clears a field.',
        scope: AgentScope::ContactsWrite,
        properties: self::FACILITY_PROPERTIES + ['facility_id' => ['type' => 'integer']],
        required: ['facility_id'],
        readOnly: false,
        idempotent: true)]
    public function updateFacility(ToolArguments $args, User $user): array
    {
        $facility = $this->facility($args);
        $this->fillFacility($facility, $args);
        $this->kit->validate($facility);
        $this->kit->em->flush();

        return $this->facilityDetails($facility);
    }

    /**
     * @return array<string, mixed>
     */
    #[Tool('set_facility_person', 'Puts a reported person on the seat of a facility (representative or deputy). A known e-mail address reuses that contact, otherwise a contact is created. clear = true empties the seat.',
        scope: AgentScope::ContactsWrite,
        properties: [
            'facility_id' => ['type' => 'integer'],
            'role' => ['type' => 'string', 'enum' => ['representative', 'deputy']],
            'first_name' => ['type' => 'string'],
            'last_name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'reported_on' => ['type' => 'string', 'description' => 'Date of the report, default today'],
            'remove_previous' => ['type' => 'boolean', 'description' => 'Delete the contact who held the seat so far, if they hold no other seat'],
            'clear' => ['type' => 'boolean'],
        ],
        required: ['facility_id', 'role'],
        readOnly: false,
        destructive: true)]
    public function setFacilityPerson(ToolArguments $args, User $user): array
    {
        $facility = $this->facility($args);
        $role = FacilityRole::from((string) $args->choice('role', ['representative', 'deputy']));
        if ($args->bool('clear')) {
            $this->seats->release($facility, $role, $args->bool('remove_previous'));

            return $this->facilityDetails($facility);
        }

        $person = new FacilityPerson();
        $person->firstName = $args->optionalString('first_name');
        $person->lastName = $args->optionalString('last_name');
        $person->email = $args->optionalString('email');
        $person->phone = $args->optionalString('phone');
        $person->reportedOn = $args->date('reported_on')?->setTime(0, 0) ?? $person->reportedOn;
        $person->removePrevious = $args->bool('remove_previous');
        $this->kit->validate($person);
        $this->seats->assign($facility, $role, $person, $user);

        return $this->facilityDetails($facility);
    }

    private const array CONTACT_PROPERTIES = [
        'salutation' => ['type' => 'string'],
        'first_name' => ['type' => 'string'],
        'last_name' => ['type' => 'string'],
        'company' => ['type' => 'string', 'description' => 'Company, authority or institution'],
        'position' => ['type' => 'string'],
        'email' => ['type' => 'string'],
        'email2' => ['type' => 'string'],
        'phone' => ['type' => 'string'],
        'mobile' => ['type' => 'string'],
        'street' => ['type' => 'string'],
        'postal_code' => ['type' => 'string'],
        'city' => ['type' => 'string'],
        'website' => ['type' => 'string'],
        'birthday' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'tags' => ['type' => 'string', 'description' => 'Comma separated'],
        'notes' => ['type' => 'string'],
        'group_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Distribution lists of the same organization'],
    ];

    private const array FACILITY_PROPERTIES = [
        'name' => ['type' => 'string'],
        'number' => ['type' => 'string', 'description' => 'Official number of the facility'],
        'carrier' => ['type' => 'string', 'description' => 'Carrier (Träger)'],
        'district' => ['type' => 'string'],
        'street' => ['type' => 'string'],
        'postal_code' => ['type' => 'string'],
        'city' => ['type' => 'string'],
        'email' => ['type' => 'string'],
        'phone' => ['type' => 'string'],
        'notes' => ['type' => 'string'],
    ];

    private function fillContact(Contact $contact, ToolArguments $args, User $user): void
    {
        foreach (self::CONTACT_FIELDS as $argument => $setter) {
            if ($args->has($argument)) {
                $value = $args->string($argument);
                $contact->{$setter}('' === $value ? null : $value);
            }
        }
        if ($args->has('birthday')) {
            $contact->setBirthday($args->date('birthday')?->setTime(0, 0));
        }
        if ($args->has('group_ids')) {
            $ids = $args->ints('group_ids');
            $groups = $this->visibleGroups($user);
            foreach (array_diff($ids, array_map(static fn (ContactGroup $g): ?int => $g->getId(), $groups)) as $unknown) {
                throw new ToolError(\sprintf('Unknown group %d. Use list_contact_groups.', $unknown));
            }
            foreach ($groups as $group) {
                \in_array($group->getId(), $ids, true) ? $contact->addGroup($group) : $contact->removeGroup($group);
            }
        }
    }

    private function fillFacility(Facility $facility, ToolArguments $args): void
    {
        foreach (self::FACILITY_FIELDS as $argument => $setter) {
            if ($args->has($argument)) {
                $value = $args->string($argument);
                $facility->{$setter}('' === $value ? null : $value);
            }
        }
    }

    /**
     * @return list<ContactGroup>
     */
    private function visibleGroups(User $user): array
    {
        return array_values(array_filter($this->groups->findForUser($user), fn (ContactGroup $g): bool => $this->agent->allows($g->getOrganization())));
    }

    private function group(User $user, int $id): ContactGroup
    {
        foreach ($this->visibleGroups($user) as $group) {
            if ($group->getId() === $id) {
                return $group;
            }
        }
        throw new ToolError('Unknown group_id. Use list_contact_groups.');
    }

    private function contact(ToolArguments $args): Contact
    {
        $contact = $this->kit->find(Contact::class, $args->requiredInt('contact_id'));
        $this->kit->check(ContactVoter::EDIT, $contact, $contact->getOrganization());

        return $contact;
    }

    private function facility(ToolArguments $args): Facility
    {
        $facility = $this->kit->find(Facility::class, $args->requiredInt('facility_id'));
        $this->kit->check(FacilityVoter::EDIT, $facility, $facility->getOrganization());

        return $facility;
    }

    /**
     * @return array<string, mixed>
     */
    private function contactSummary(Contact $contact): array
    {
        return [
            'id' => $contact->getId(),
            'name' => $contact->getDisplayName(),
            'company' => $contact->getCompany(),
            'position' => $contact->getPosition(),
            'email' => $contact->getEmail(),
            'phone' => $contact->getPhone() ?? $contact->getMobile(),
            'organization' => ToolKit::org($contact->getOrganization()),
            'url' => $this->kit->url('contact_show', ['id' => $contact->getId()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactDetails(Contact $contact): array
    {
        $seats = [];
        if (true === $contact->getOrganization()?->hasFeature(Feature::Facilities)) {
            foreach ($this->facilities->findForContact($contact) as $facility) {
                foreach (FacilityRole::cases() as $role) {
                    if ($facility->getPerson($role) === $contact) {
                        $seats[] = ['facility_id' => $facility->getId(), 'facility' => $facility->getName(), 'role' => $role->value];
                    }
                }
            }
        }

        return $this->contactSummary($contact) + [
            'salutation' => $contact->getSalutation(),
            'first_name' => $contact->getFirstName(),
            'last_name' => $contact->getLastName(),
            'email2' => $contact->getEmail2(),
            'mobile' => $contact->getMobile(),
            'street' => $contact->getStreet(),
            'postal_code' => $contact->getPostalCode(),
            'city' => $contact->getCity(),
            'website' => $contact->getWebsite(),
            'birthday' => ToolKit::day($contact->getBirthday()),
            'tags' => $contact->getTagList(),
            'notes' => $contact->getNotes(),
            'groups' => array_values(array_map(static fn (ContactGroup $g): array => ['id' => $g->getId(), 'name' => $g->getName()], $contact->getGroups()->toArray())),
            'facility_seats' => $seats,
            'created' => ToolKit::time($contact->getCreatedAt()),
            'via_agent' => $contact->getAgentName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facilityDetails(Facility $facility): array
    {
        $person = static fn (?Contact $c): ?array => null === $c ? null : ['contact_id' => $c->getId(), 'name' => $c->getDisplayName(), 'email' => $c->getEmail(), 'phone' => $c->getPhone() ?? $c->getMobile()];

        return [
            'id' => $facility->getId(),
            'name' => $facility->getName(),
            'number' => $facility->getNumber(),
            'carrier' => $facility->getCarrier(),
            'district' => $facility->getDistrict(),
            'street' => $facility->getStreet(),
            'postal_code' => $facility->getPostalCode(),
            'city' => $facility->getCity(),
            'email' => $facility->getEmail(),
            'phone' => $facility->getPhone(),
            'notes' => $facility->getNotes(),
            'representative' => $person($facility->getRepresentative()),
            'deputy' => $person($facility->getDeputy()),
            'reported_on' => ToolKit::day($facility->getReportedOn()),
            'needs_review' => $facility->needsReview(),
            'organization' => ToolKit::org($facility->getOrganization()),
            'url' => $this->kit->url('facility_show', ['id' => $facility->getId()]),
        ];
    }
}
