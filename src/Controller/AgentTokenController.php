<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agent\AgentScope;
use App\Entity\AgentToken;
use App\Entity\User;
use App\Enum\Feature;
use App\Repository\AgentTokenRepository;
use App\Repository\OrganizationRepository;
use App\Security\SecurityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Profile section "KI-Agenten": personal tokens for scripts and Claude Code, connected apps (OAuth), revoking.
 */
#[Route('/profile/agents')]
final class AgentTokenController extends AbstractController
{
    private const string NEW_SECRET = 'agent_token_secret';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgentTokenRepository $tokens,
        private readonly SecurityLog $log,
    ) {
    }

    #[Route('', name: 'agent_tokens', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        return $this->render('profile/agents.html.twig', [
            'section' => 'agents',
            'tokens' => $this->tokens->findActiveFor($user),
            'organizations' => $organizations->findForUser($user, Feature::Agents),
            'scopes' => AgentScope::cases(),
            'day_choices' => AgentToken::DAY_CHOICES,
            // shown exactly once after creating
            'new_secret' => $request->getSession()->remove(self::NEW_SECRET),
            'mcp_url' => $this->generateUrl('mcp', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/new', name: 'agent_token_new', methods: ['POST'])]
    #[IsCsrfTokenValid('agent-token-new')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $payload = $request->getPayload();
        $name = trim($payload->getString('name'));
        $scopes = array_values(array_filter(array_map(AgentScope::tryFrom(...), $payload->all('scopes'))));
        $days = $payload->getInt('days');
        if ('' === $name || [] === $scopes || !\in_array($days, AgentToken::DAY_CHOICES, true)) {
            $this->addFlash('error', '' === $name ? 'agent.token.name_required' : 'agent.scopes_required');

            return $this->redirectToRoute('agent_tokens');
        }
        $token = new AgentToken($user, $name, $scopes);
        $secret = $token->issuePersonal($days);
        $this->em->persist($token);
        $this->em->flush();
        $this->log->record('agent_token_created', $user, $token->getName());
        $request->getSession()->set(self::NEW_SECRET, $secret);

        return $this->redirectToRoute('agent_tokens');
    }

    #[Route('/{id}/revoke', name: 'agent_token_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsCsrfTokenValid('agent-token-revoke')]
    public function revoke(AgentToken $token, #[CurrentUser] User $user): Response
    {
        if ($token->getUser() !== $user) {
            throw $this->createNotFoundException();
        }
        $this->em->remove($token);
        $this->em->flush();
        $this->log->record('agent_token_revoked', $user, $token->getName());
        $this->addFlash('success', 'agent.token.revoked');

        return $this->redirectToRoute('agent_tokens');
    }
}
