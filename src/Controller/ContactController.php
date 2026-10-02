<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\ContactFormType;
use App\Repository\ContactRepository;
use App\Repository\OrganizationRepository;
use App\Security\Voter\ContactVoter;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contacts')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContactRepository $contacts,
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
        $organizations = $this->organizations->findForUser($user);
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
    public function show(Request $request, Contact $contact, #[CurrentUser] User $user): Response
    {
        return $this->render('contact/show.html.twig', $this->listContext($request, $user) + ['contact' => $contact]);
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

    private function handleForm(Request $request, User $user, Contact $contact, string $flash): Response
    {
        $isNew = null === $contact->getId();
        $form = $this->createForm(ContactFormType::class, $contact, ['organizations' => $this->organizations->findForUser($user)]);
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
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user): array
    {
        $query = trim($request->query->getString('q'));

        return ['contacts' => $this->contacts->search($user, $query), 'query' => $query];
    }
}
