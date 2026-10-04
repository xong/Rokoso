<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\SecurityEventRepository;
use App\Repository\UserRepository;
use App\Security\AccountDeleter;
use App\Security\SecurityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Operator area for the whole installation: accounts (block, delete), organizations, security log.
 * Content of the organizations stays invisible here.
 */
#[Route('/admin')]
#[IsGranted('ROLE_PLATFORM_ADMIN')]
final class PlatformAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly SecurityLog $log)
    {
    }

    #[Route('', name: 'platform_users')]
    public function users(Request $request, UserRepository $users): Response
    {
        $query = $request->query->getString('q');

        return $this->render('platform/users.html.twig', [
            'section' => 'users',
            'query' => $query,
            'users' => $users->search($query),
        ]);
    }

    #[Route('/users/{id<\d+>}/block', name: 'platform_user_block', methods: ['POST'])]
    #[IsCsrfTokenValid('platform-user')]
    public function block(User $account, #[CurrentUser] User $user): Response
    {
        if ($account === $user || null !== $account->getDeletedAt()) {
            throw $this->createAccessDeniedException();
        }
        $blocked = null === $account->getBlockedAt();
        $account->setBlocked($blocked);
        if ($blocked) {
            // ends running sessions and remember-me cookies
            $account->renewSessionStamp();
        }
        $this->em->flush();
        $this->log->record($blocked ? 'account_blocked' : 'account_unblocked', $account, null, $user);
        $this->addFlash('success', $blocked ? 'platform.blocked' : 'platform.unblocked');

        return $this->redirectToRoute('platform_users');
    }

    #[Route('/users/{id<\d+>}/delete', name: 'platform_user_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('platform-user')]
    public function delete(User $account, #[CurrentUser] User $user, AccountDeleter $deleter, TranslatorInterface $translator): Response
    {
        if ($account === $user || null !== $account->getDeletedAt()) {
            throw $this->createAccessDeniedException();
        }
        $blockers = $deleter->blockers($account);
        if ([] !== $blockers) {
            $this->addFlash('error', $translator->trans('platform.delete_blocked', [
                '%organizations%' => implode(', ', array_map(static fn ($o): string => $o->getName(), $blockers)),
            ]));

            return $this->redirectToRoute('platform_users');
        }
        $deleter->delete($account, $user);
        $this->addFlash('success', 'platform.deleted');

        return $this->redirectToRoute('platform_users');
    }

    #[Route('/organizations', name: 'platform_organizations')]
    public function organizations(OrganizationRepository $organizations): Response
    {
        return $this->render('platform/organizations.html.twig', [
            'section' => 'organizations',
            'organizations' => $organizations->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/log', name: 'platform_log')]
    public function log(SecurityEventRepository $events): Response
    {
        return $this->render('platform/log.html.twig', [
            'section' => 'log',
            'events' => $events->findLatest(null, 200),
        ]);
    }
}
