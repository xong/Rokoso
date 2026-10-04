<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Form\ContactFormType;
use App\Form\ContactGroupFormType;
use App\Notification\ActivityNotifier;
use App\Repository\CommentRepository;
use App\Repository\ContactGroupRepository;
use App\Repository\ContactRepository;
use App\Repository\MeetingRepository;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Security\Voter\ContactVoter;
use App\Security\Voter\OrganizationVoter;
use App\Service\ImageUploader;
use App\Service\VCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/contacts')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContactRepository $contacts,
        private readonly ContactGroupRepository $groups,
        private readonly OrganizationRepository $organizations,
        private readonly ImageUploader $uploader,
    ) {
    }

    #[Route('', name: 'contact_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('contact/index.html.twig', $this->listContext($request, $user));
    }

    #[Route('/new', name: 'contact_new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $contact = new Contact($user);
        $organizations = $this->organizations->findForUser($user, Feature::Contacts);
        $contact->setOrganization($organizations[0] ?? null);
        if ('' !== $request->query->getString('email')) {
            $contact->setEmail($request->query->getString('email'));
            $name = $request->query->getString('name');
            if ('' !== $name) {
                $parts = explode(' ', $name);
                $contact->setLastName(array_pop($parts))->setFirstName(implode(' ', $parts));
            }
        }

        return $this->handleForm($request, $user, $contact, 'contact.created');
    }

    #[Route('/{id<\d+>}', name: 'contact_show')]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    public function show(Request $request, Contact $contact, #[CurrentUser] User $user, CommentRepository $comments, MessageRepository $messages, MeetingRepository $meetings): Response
    {
        // history: mails from/to the contact and meetings as guest, newest first
        $history = [];
        foreach ($messages->findForAddresses($user, $contact->getEmails()) as $message) {
            $history[] = ['date' => $message->getDate(), 'message' => $message, 'meeting' => null];
        }
        foreach ($meetings->findWithGuest($user, $contact->getEmails()) as $meeting) {
            $history[] = ['date' => $meeting->getStartsAt(), 'message' => null, 'meeting' => $meeting];
        }
        usort($history, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $this->render('contact/show.html.twig', $this->listContext($request, $user) + [
            'contact' => $contact,
            'colleagues' => $this->contacts->findColleagues($contact, $user),
            'history' => $history,
            'comments' => $comments->forTarget($contact),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'contact_edit')]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    public function edit(Request $request, Contact $contact, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($request, $user, $contact, 'flash.saved');
    }

    #[Route('/{id<\d+>}/delete', name: 'contact_delete', methods: ['POST'])]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    #[IsCsrfTokenValid(new Expression('"delete-contact-" ~ args["contact"].getId()'))]
    public function delete(Contact $contact): Response
    {
        $this->uploader->remove($contact->getPhoto());
        $this->em->remove($contact);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('contact_index');
    }

    #[Route('/{id<\d+>}/comment', name: 'contact_comment', methods: ['POST'])]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    #[IsCsrfTokenValid('contact-comment')]
    public function comment(Request $request, Contact $contact, ActivityNotifier $notifier, #[CurrentUser] User $user): Response
    {
        $comment = Comment::onContact($contact, $user)->setBody($request->getPayload()->getString('body'));
        if ('' !== $comment->getBody()) {
            $this->em->persist($comment);
            $this->em->flush();
            $notifier->commentAdded($comment, $user);
        }

        return $this->redirect($this->generateUrl('contact_show', ['id' => $contact->getId()]).'#comments');
    }

    #[Route('/{id<\d+>}/comment/{comment<\d+>}/delete', name: 'contact_comment_delete', methods: ['POST'])]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    #[IsCsrfTokenValid('contact-comment')]
    public function deleteComment(Contact $contact, Comment $comment, #[CurrentUser] User $user): Response
    {
        if ($comment->getContact() !== $contact || $comment->getAuthor() !== $user) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($comment);
        $this->em->flush();

        return $this->redirect($this->generateUrl('contact_show', ['id' => $contact->getId()]).'#comments');
    }

    #[Route('/{id<\d+>}/vcard', name: 'contact_vcard')]
    #[IsGranted(ContactVoter::EDIT, 'contact')]
    public function vcard(Contact $contact): Response
    {
        return $this->vcardResponse([$contact], $contact->getDisplayName());
    }

    /** Export of the current list (search and group filter). */
    #[Route('/export', name: 'contact_export')]
    public function export(Request $request, #[CurrentUser] User $user): Response
    {
        $context = $this->listContext($request, $user);

        return $this->vcardResponse($context['contacts'], null !== $context['group'] ? $context['group']->getName() : 'kontakte');
    }

    #[Route('/import', name: 'contact_import')]
    public function import(Request $request, #[CurrentUser] User $user, TranslatorInterface $translator): Response
    {
        $organizations = $this->organizations->findForUser($user, Feature::Contacts);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact-import', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $file = $request->files->get('file');
            $organizationId = $request->getPayload()->getInt('organization');
            $organization = null;
            foreach ($organizations as $candidate) {
                if ($candidate->getId() === $organizationId) {
                    $organization = $candidate;
                }
            }
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > 5_000_000) {
                $this->addFlash('error', 'contact.import_invalid');

                return $this->redirectToRoute('contact_import');
            }

            $known = $this->contacts->knownEmails($user);
            $imported = 0;
            $skipped = 0;
            foreach (VCard::parse((string) file_get_contents($file->getPathname()), $user) as $contact) {
                $emails = $contact->getEmails();
                if ([] !== array_intersect_key(array_flip($emails), $known)) {
                    ++$skipped;
                    continue;
                }
                foreach ($emails as $email) {
                    $known[$email] = true;
                }
                $this->em->persist($contact->setOrganization($organization));
                ++$imported;
            }
            $this->em->flush();
            $this->addFlash('success', $translator->trans('contact.imported', ['%count%' => $imported, '%skipped%' => $skipped]));

            return $this->redirectToRoute('contact_index');
        }

        return $this->render('contact/import.html.twig', $this->listContext($request, $user) + ['organizations' => $organizations]);
    }

    #[Route('/groups', name: 'contact_group_index')]
    public function groups(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('contact/groups.html.twig', $this->listContext($request, $user));
    }

    #[Route('/groups/new', name: 'contact_group_new')]
    public function newGroup(Request $request, #[CurrentUser] User $user): Response
    {
        $organizations = $this->organizations->findForUser($user, Feature::Contacts);
        $organization = $organizations[0] ?? null;
        foreach ($organizations as $candidate) {
            if ($candidate->getId() === $request->query->getInt('organization')) {
                $organization = $candidate;
            }
        }
        if (null === $organization) {
            throw $this->createAccessDeniedException();
        }

        return $this->handleGroupForm($request, $user, new ContactGroup($organization), $organizations);
    }

    #[Route('/groups/{id<\d+>}', name: 'contact_group_show')]
    public function showGroup(Request $request, ContactGroup $group, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $group->getOrganization());
        $request->query->set('group', (string) $group->getId());

        return $this->render('contact/group.html.twig', ['group' => $group] + $this->listContext($request, $user));
    }

    #[Route('/groups/{id<\d+>}/edit', name: 'contact_group_edit')]
    public function editGroup(Request $request, ContactGroup $group, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $group->getOrganization());

        return $this->handleGroupForm($request, $user, $group, []);
    }

    #[Route('/groups/{id<\d+>}/delete', name: 'contact_group_delete', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"delete-contact-group-" ~ args["group"].getId()'))]
    public function deleteGroup(ContactGroup $group): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $group->getOrganization());
        $this->em->remove($group);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('contact_group_index');
    }

    #[Route('/groups/{id<\d+>}/vcard', name: 'contact_group_vcard')]
    public function groupVcard(ContactGroup $group): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $group->getOrganization());

        return $this->vcardResponse($group->getContacts(), $group->getName());
    }

    /**
     * @param list<Organization> $organizations choices when creating (empty when editing)
     */
    private function handleGroupForm(Request $request, User $user, ContactGroup $group, array $organizations): Response
    {
        $isNew = null === $group->getId();
        $form = $this->createForm(ContactGroupFormType::class, $group, ['contacts' => $this->contacts->findInOrganization($group->getOrganization())]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($group);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'contact_group.created' : 'flash.saved');

            return $this->redirectToRoute('contact_group_show', ['id' => $group->getId()]);
        }

        return $this->render('contact/group_form.html.twig', $this->listContext($request, $user) + [
            'form' => $form,
            'edited' => $isNew ? null : $group,
            'organization' => $group->getOrganization(),
            'organizations' => $organizations,
        ]);
    }

    /**
     * @param iterable<Contact> $contacts
     */
    private function vcardResponse(iterable $contacts, string $name): Response
    {
        $filename = (new AsciiSlugger('de'))->slug($name)->lower()->toString() ?: 'kontakte';

        return new Response(VCard::export($contacts), Response::HTTP_OK, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename.'.vcf'),
        ]);
    }

    private function handleForm(Request $request, User $user, Contact $contact, string $flash): Response
    {
        $isNew = null === $contact->getId();
        $form = $this->createForm(ContactFormType::class, $contact, [
            'organizations' => $this->organizations->findForUser($user, Feature::Contacts),
            'groups' => $this->groups->findForUser($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storePhoto($form, $contact);
            $this->em->persist($contact);
            $this->em->flush();
            $this->addFlash('success', $flash);

            return $this->redirectToRoute('contact_show', ['id' => $contact->getId()]);
        }
        if ($form->isSubmitted() && !$isNew) {
            $this->em->refresh($contact);
        }

        return $this->render('contact/form.html.twig', $this->listContext($request, $user) + [
            'contact' => $isNew ? null : $contact,
            'form' => $form,
            'institutions' => $this->contacts->distinctValues($user, 'company'),
            'positions' => $this->contacts->distinctValues($user, 'position'),
        ]);
    }

    /**
     * @param FormInterface<Contact> $form
     */
    private function storePhoto(FormInterface $form, Contact $contact): void
    {
        $file = $form->get('photoFile')->getData();
        if ($file instanceof UploadedFile) {
            $contact->setPhoto($this->uploader->store($file, 'contacts', $contact->getPhoto()));
        } elseif (true === $form->get('removePhoto')->getData()) {
            $this->uploader->remove($contact->getPhoto());
            $contact->setPhoto(null);
        }
    }

    /**
     * Contact list (search, group filter) for the list column.
     *
     * @return array{contacts: list<Contact>, query: string, groups: list<ContactGroup>, group: ?ContactGroup}
     */
    private function listContext(Request $request, User $user): array
    {
        $query = trim($request->query->getString('q'));
        $groups = $this->groups->findForUser($user);
        $group = null;
        foreach ($groups as $candidate) {
            if ($candidate->getId() === $request->query->getInt('group')) {
                $group = $candidate;
            }
        }

        return ['contacts' => $this->contacts->search($user, $query, $group), 'query' => $query, 'groups' => $groups, 'group' => $group];
    }
}
