<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\PublicTopic;
use App\Entity\User;
use App\Form\PublicSettingsFormType;
use App\Form\PublicTopicFormType;
use App\Repository\ContactGroupRepository;
use App\Repository\MailAccountRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Repository\PublicSettingsRepository;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Settings of the public participation pages of an organization (admins only).
 */
#[Route('/organizations/{id<\d+>}/public')]
#[IsGranted(OrganizationVoter::MANAGE, 'organization')]
final class PublicSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicSettingsRepository $settings,
        private readonly OrganizationRepository $organizations,
        private readonly ProjectRepository $projects,
    ) {
    }

    #[Route('', name: 'public_settings')]
    public function edit(Request $request, Organization $organization, #[CurrentUser] User $user, MailAccountRepository $accounts, ContactGroupRepository $groups, TranslatorInterface $translator): Response
    {
        $settings = $this->settings->forOrganization($organization);
        $subscribable = \count($groups->findPublicSubscribe($organization));
        $form = $this->createForm(PublicSettingsFormType::class, $settings, ['accounts' => $accounts->findForOrganization($organization)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $settings->isSubscribeEnabled() && 0 === $subscribable) {
            $form->get('subscribeEnabled')->addError(new FormError($translator->trans('public.subscribe_groups_required', [], 'validators')));
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($settings);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('public_settings', ['id' => $organization->getId()]);
        }

        return $this->render('public_settings/edit.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'settings' => $settings,
            'subscribable' => $subscribable,
            'form' => $form,
        ], $form->isSubmitted() ? new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/topics/new', name: 'public_topic_new')]
    #[Route('/topics/{topic<\d+>}/edit', name: 'public_topic_edit')]
    public function topic(Request $request, Organization $organization, #[CurrentUser] User $user, ?PublicTopic $topic = null): Response
    {
        $settings = $this->settings->forOrganization($organization);
        if (null !== $topic && $topic->getSettings() !== $settings) {
            throw $this->createNotFoundException();
        }
        $topic ??= new PublicTopic($settings);
        $form = $this->createForm(PublicTopicFormType::class, $topic, [
            'projects' => $this->projects->findBy(['organization' => $organization, 'archivedAt' => null], ['name' => 'ASC']),
            'users' => $organization->getMembers(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $settings->addTopic($topic);
            $this->em->persist($settings);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('public_settings', ['id' => $organization->getId(), '_fragment' => 'topics']);
        }

        return $this->render('public_settings/topic.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'topic' => $topic,
            'form' => $form,
        ]);
    }

    #[Route('/topics/{topic<\d+>}/delete', name: 'public_topic_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('public-topic')]
    public function deleteTopic(Organization $organization, PublicTopic $topic): Response
    {
        $settings = $this->settings->forOrganization($organization);
        if ($topic->getSettings() !== $settings) {
            throw $this->createNotFoundException();
        }
        $settings->removeTopic($topic);
        $this->em->flush();
        $this->addFlash('success', 'public.topic.deleted');

        return $this->redirectToRoute('public_settings', ['id' => $organization->getId(), '_fragment' => 'topics']);
    }
}
