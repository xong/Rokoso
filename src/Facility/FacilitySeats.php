<?php

declare(strict_types=1);

namespace App\Facility;

use App\Entity\Contact;
use App\Entity\Facility;
use App\Entity\User;
use App\Enum\FacilityRole;
use App\Repository\ContactRepository;
use App\Repository\FacilityRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Puts reported people on the seats of a facility. People are contacts of the organization: a known e-mail address
 * reuses the contact, otherwise a new one is created. Whoever is replaced stays a contact unless asked to delete them.
 */
final readonly class FacilitySeats
{
    public function __construct(
        private EntityManagerInterface $em,
        private ContactRepository $contacts,
        private FacilityRepository $facilities,
        private ImageUploader $uploader,
        private TranslatorInterface $translator,
    ) {
    }

    public function assign(Facility $facility, FacilityRole $role, FacilityPerson $person, User $user): Contact
    {
        $email = null === $person->email || '' === trim($person->email) ? null : mb_strtolower(trim($person->email));
        $contact = null === $email ? null : $this->contacts->findOneInOrganizationByEmail($facility->getOrganization(), $email);
        if (null === $contact) {
            $contact = (new Contact($user))
                ->setOrganization($facility->getOrganization())
                ->setFirstName($person->firstName)
                ->setLastName($person->lastName)
                ->setEmail($email)
                ->setPhone($person->phone)
                ->setCompany($facility->getName())
                ->setPosition($this->translator->trans($role->label()));
            $this->em->persist($contact);
        } else {
            // known person: only fill gaps, their contact may hold more than the report
            if (null === $contact->getFirstName() && null === $contact->getLastName()) {
                $contact->setFirstName($person->firstName)->setLastName($person->lastName);
            }
            if (null === $contact->getPhone() && null === $contact->getMobile()) {
                $contact->setPhone($person->phone);
            }
            if (null === $contact->getCompany()) {
                $contact->setCompany($facility->getName());
            }
        }

        $previous = $facility->getPerson($role);
        $facility->setPerson($role, $contact)->setReportedOn($person->reportedOn);
        $this->em->flush();
        if ($person->removePrevious && null !== $previous && $previous !== $contact) {
            $this->deleteIfUnused($previous);
        }

        return $contact;
    }

    public function release(Facility $facility, FacilityRole $role, bool $deleteContact): void
    {
        $previous = $facility->getPerson($role);
        $facility->setPerson($role, null);
        $this->em->flush();
        if ($deleteContact && null !== $previous) {
            $this->deleteIfUnused($previous);
        }
    }

    /** The contact holds no seat besides the given one, so replacing them may delete the contact. */
    public function isOnlySeat(Contact $contact, Facility $facility): bool
    {
        return [] === array_filter($this->facilities->findForContact($contact), static fn (Facility $f): bool => $f !== $facility);
    }

    private function deleteIfUnused(Contact $contact): void
    {
        if ([] !== $this->facilities->findForContact($contact)) {
            return;
        }
        $this->uploader->remove($contact->getPhoto());
        $this->em->remove($contact);
        $this->em->flush();
    }
}
