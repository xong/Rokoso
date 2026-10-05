<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\TextSnippet;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Form\InvitationFormType;
use App\Form\MembershipFormType;
use App\Form\OrganizationFormType;
use App\Organization\HandoverService;
use App\Repository\BlockedSenderRepository;
use App\Repository\InvitationRepository;
use App\Repository\MailAccountRepository;
use App\Repository\MailRuleRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Security\SecurityLog;
use App\Security\Voter\OrganizationVoter;
use App\Service\ImageUploader;
use App\Service\InvitationManager;
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
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/organizations')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizations,
        private readonly ImageUploader $uploader,
        private readonly SecurityLog $log,
    ) {
    }

    #[Route('', name: 'organization_index')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('organization/index.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
        ]);
    }

    #[Route('/new', name: 'organization_new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationFormType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeLogo($form, $organization);
            $organization->addMember($user, OrganizationRole::Admin);
            $this->em->persist($organization);
            $this->em->flush();
            $this->addFlash('success', 'organization.created');

            return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
        }

        return $this->render('organization/form.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'organization_show')]
    #[IsGranted(OrganizationVoter::VIEW, 'organization')]
    public function show(Organization $organization, #[CurrentUser] User $user, InvitationRepository $invitations, MailAccountRepository $mailAccounts, MailRuleRepository $mailRules, BlockedSenderRepository $blockedSenders, ProjectRepository $projects): Response
    {
        $inviteForm = $this->createForm(InvitationFormType::class, null, [
            'action' => $this->generateUrl('organization_invite', ['id' => $organization->getId()]),
        ]);

        return $this->render('organization/show.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'invitations' => $invitations->findPending($organization),
            'invite_form' => $inviteForm,
            'mail_accounts' => $mailAccounts->findForOrganization($organization),
            'mail_rules' => $mailRules->findForOrganization($organization),
            'blocked_senders' => $blockedSenders->findForOrganization($organization),
            'org_projects' => $projects->findBy(['organization' => $organization], ['name' => 'ASC']),
            'snippets' => $this->em->getRepository(TextSnippet::class)->findBy(['organization' => $organization], ['title' => 'ASC']),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'organization_edit')]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function edit(Request $request, Organization $organization, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(OrganizationFormType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeLogo($form, $organization);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($organization);
        }

        return $this->render('organization/form.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'organization_delete', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    #[IsCsrfTokenValid(new Expression('"delete-organization-" ~ args["organization"].getId()'))]
    public function delete(Organization $organization, #[CurrentUser] User $user): Response
    {
        $name = $organization->getName();
        $this->uploader->remove($organization->getLogo());
        $this->em->remove($organization);
        $this->em->flush();
        $this->log->record('organization_deleted', $user, $name);
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('organization_index');
    }

    /**
     * Role, function, term, voting right and (for guests) released projects of a membership.
     */
    #[Route('/{id<\d+>}/members/{membership<\d+>}/edit', name: 'organization_member_edit')]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function editMember(Request $request, Organization $organization, Membership $membership, #[CurrentUser] User $user, ProjectRepository $projects): Response
    {
        $this->assertBelongs($organization, $membership);
        $wasAdmin = $membership->isAdmin();
        $oldRole = $membership->getRole();
        $choices = array_values(array_filter($projects->findBy(['organization' => $organization], ['name' => 'ASC']),
            static fn (Project $p): bool => !$p->isArchived() || $membership->getGuestProjects()->contains($p)));
        $form = $this->createForm(MembershipFormType::class, $membership, ['projects' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasAdmin && !$membership->isAdmin() && 0 === $organization->countAdmins()) {
                $this->em->refresh($membership);
                $this->addFlash('error', 'organization.last_admin');

                return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
            }
            if (!$membership->isGuest()) {
                $membership->getGuestProjects()->clear();
            }
            $this->em->flush();
            if ($oldRole !== $membership->getRole()) {
                $this->log->record('role_changed', $membership->getUser(),
                    $organization->getName().': '.$oldRole->value.' → '.$membership->getRole()->value, $user);
            }
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
        }

        return $this->render('organization/member.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'membership' => $membership,
            'form' => $form,
        ]);
    }

    /**
     * Change of office: hands over assignments, tasks, rules, projects and minute taking of one person to another.
     */
    #[Route('/{id<\d+>}/handover', name: 'organization_handover')]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function handover(Request $request, Organization $organization, #[CurrentUser] User $user, HandoverService $handover, TranslatorInterface $translator): Response
    {
        $payload = $request->getPayload();
        $from = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('handover', $payload->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $from = $organization->getMemberships()->findFirst(static fn (int $k, Membership $m): bool => (string) $m->getId() === $payload->getString('from'));
            $to = $organization->getMemberships()->findFirst(static fn (int $k, Membership $m): bool => (string) $m->getId() === $payload->getString('to'));
            $scopes = array_values(array_filter($payload->all('scopes'), \is_string(...)));
            if (null === $from || null === $to || $from === $to || !$to->isFull() || [] === $scopes) {
                $this->addFlash('error', 'handover.invalid');
            } else {
                $counts = $handover->transfer($organization, $from->getUser(), $to->getUser(), $scopes);
                if ($payload->getBoolean('end_term') && $from->isActive()) {
                    $from->setTermEndsOn(new \DateTimeImmutable('yesterday'));
                }
                $this->em->flush();
                $this->addFlash('success', $translator->trans('handover.done_flash', [
                    '%from%' => $from->getUser()->getName(),
                    '%to%' => $to->getUser()->getName(),
                    '%count%' => array_sum($counts),
                ]));

                return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
            }
        }

        return $this->render('organization/handover.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'scopes' => HandoverService::SCOPES,
            'preselected' => $request->query->getInt('from'),
        ]);
    }

    /**
     * Remove a member (admins) or leave the organization (any member for themselves).
     */
    #[Route('/{id<\d+>}/members/{membership<\d+>}/remove', name: 'organization_member_remove', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::VIEW, 'organization')]
    #[IsCsrfTokenValid('member-remove')]
    public function removeMember(Organization $organization, Membership $membership, #[CurrentUser] User $user): Response
    {
        $this->assertBelongs($organization, $membership);
        $self = $membership->getUser() === $user;
        if (!$self) {
            $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE, $organization);
        }
        if ($membership->isAdmin() && 1 === $organization->countAdmins()) {
            $this->addFlash('error', 'organization.last_admin');

            return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
        }

        $organization->getMemberships()->removeElement($membership);
        $this->em->flush();
        $this->log->record('member_removed', $membership->getUser(), $organization->getName(), $user);
        $this->addFlash('success', $self ? 'organization.left' : 'organization.member_removed');

        return $self
            ? $this->redirectToRoute('organization_index')
            : $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }

    #[Route('/{id<\d+>}/invite', name: 'organization_invite', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function invite(Request $request, Organization $organization, #[CurrentUser] User $user, InvitationManager $invitations): Response
    {
        $form = $this->createForm(InvitationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string, role: OrganizationRole} $data */
            $data = $form->getData();
            $email = mb_strtolower(trim($data['email']));
            // guests and former members may be invited again (as members)
            $alreadyMember = array_any($organization->getMembers(), static fn (User $u): bool => $u->getEmail() === $email);

            if ($alreadyMember) {
                $this->addFlash('info', 'organization.already_member');
            } else {
                $account = $invitations->invite($organization, $email, $data['role'], $user);
                $this->addFlash('success', null !== $account ? 'organization.invited_account' : 'organization.invited');
            }
        } else {
            $this->addFlash('error', 'organization.invite_invalid');
        }

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }

    #[Route('/{id<\d+>}/invitations/{invitation<\d+>}/revoke', name: 'organization_invitation_revoke', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    #[IsCsrfTokenValid('invitation-revoke')]
    public function revokeInvitation(Organization $organization, Invitation $invitation, InvitationManager $invitations): Response
    {
        if ($invitation->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }
        $invitations->revoke($invitation);
        $this->addFlash('success', 'organization.invitation_revoked');

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }

    /**
     * @param FormInterface<Organization> $form
     */
    private function storeLogo(FormInterface $form, Organization $organization): void
    {
        $file = $form->get('logoFile')->getData();
        if ($file instanceof UploadedFile) {
            $organization->setLogo($this->uploader->store($file, 'logos', $organization->getLogo()));
        } elseif (true === $form->get('removeLogo')->getData()) {
            $this->uploader->remove($organization->getLogo());
            $organization->setLogo(null);
        }
    }

    private function assertBelongs(Organization $organization, Membership $membership): void
    {
        if ($membership->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }
    }
}
