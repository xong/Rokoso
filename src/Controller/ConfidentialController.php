<?php

declare(strict_types=1);

namespace App\Controller;

use App\Confidential\ConfidentialInbox;
use App\Entity\ConfidentialCase;
use App\Entity\User;
use App\Repository\ConfidentialCaseRepository;
use App\Repository\PublicSettingsRepository;
use App\Security\Voter\ConfidentialCaseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Anonymous confidential contact for the confidants of an organization: read, answer, close, delete.
 */
#[Route('/confidential')]
final class ConfidentialController extends AbstractController
{
    public const int MAX_LENGTH = 20000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConfidentialCaseRepository $cases,
        private readonly ConfidentialInbox $inbox,
    ) {
    }

    #[Route('', name: 'confidential_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->cases->isConfidantAnywhere($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('confidential/index.html.twig', $this->listContext($user, $request->query->getBoolean('closed')));
    }

    #[Route('/{id<\d+>}', name: 'confidential_show')]
    #[IsGranted(ConfidentialCaseVoter::VIEW, 'case')]
    public function show(ConfidentialCase $case, #[CurrentUser] User $user, PublicSettingsRepository $settings): Response
    {
        if ($case->isStaffUnread()) {
            $case->markStaffRead();
            $this->em->flush();
        }
        $public = $settings->findOneBy(['organization' => $case->getOrganization()]);

        $response = $this->render('confidential/show.html.twig', [
            'case' => $case,
            'conversation' => $this->inbox->read($case),
            'public_url' => null !== $public && $public->offers('confidential') && null !== $public->getSlug()
                ? $this->generateUrl('public_confidential', ['slug' => $public->getSlug()]) : null,
        ] + $this->listContext($user, $case->isClosed(), $case));
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('/{id<\d+>}/reply', name: 'confidential_reply', methods: ['POST'])]
    #[IsGranted(ConfidentialCaseVoter::VIEW, 'case')]
    #[IsCsrfTokenValid('confidential-reply')]
    public function reply(Request $request, ConfidentialCase $case, #[CurrentUser] User $user, PublicSettingsRepository $settings): Response
    {
        $body = trim($request->request->getString('body'));
        if ('' === $body || mb_strlen($body) > self::MAX_LENGTH) {
            $this->addFlash('error', 'confidential.reply_invalid');

            return $this->redirectToRoute('confidential_show', ['id' => $case->getId()]);
        }
        $public = $settings->findOneBy(['organization' => $case->getOrganization()]);
        $this->inbox->replyFromConfidant($case, $user, $body, null !== $public && $public->offers('confidential') ? $public->getSlug() : null);
        $this->addFlash('success', 'confidential.replied');

        return $this->redirectToRoute('confidential_show', ['id' => $case->getId()]);
    }

    #[Route('/{id<\d+>}/close', name: 'confidential_close', methods: ['POST'])]
    #[IsGranted(ConfidentialCaseVoter::VIEW, 'case')]
    #[IsCsrfTokenValid('confidential-state')]
    public function close(ConfidentialCase $case): Response
    {
        $case->isClosed() ? $case->reopen() : $case->close();
        $this->em->flush();
        $this->addFlash('success', $case->isClosed() ? 'confidential.closed' : 'confidential.reopened');

        return $this->redirectToRoute('confidential_show', ['id' => $case->getId()]);
    }

    #[Route('/{id<\d+>}/delete', name: 'confidential_delete', methods: ['POST'])]
    #[IsGranted(ConfidentialCaseVoter::VIEW, 'case')]
    #[IsCsrfTokenValid('confidential-delete')]
    public function delete(ConfidentialCase $case): Response
    {
        $this->em->remove($case);
        $this->em->flush();
        $this->addFlash('success', 'confidential.deleted');

        return $this->redirectToRoute('confidential_index');
    }

    /**
     * @return array<string, mixed>
     */
    private function listContext(User $user, bool $closed, ?ConfidentialCase $current = null): array
    {
        $items = [];
        foreach ($this->cases->findForConfidant($user, $closed) as $case) {
            $items[] = ['case' => $case, 'subject' => $this->inbox->subject($case)];
        }

        return ['items' => $items, 'closed' => $closed, 'current' => $current];
    }
}
