<?php

declare(strict_types=1);

namespace App\Agent;

use App\Repository\AgentTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Bearer token of an AI agent (personal token or OAuth access token) for the MCP endpoint; stateless.
 * Without a valid token the answer is 401 with a pointer to the OAuth metadata, so clients can start the login.
 */
final class AgentTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AgentTokenRepository $tokens,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            throw new CustomUserMessageAuthenticationException('missing token');
        }
        $token = $this->tokens->findValid($match[1]);
        if (null === $token) {
            throw new CustomUserMessageAuthenticationException('invalid token');
        }
        $request->attributes->set(AgentContext::ATTRIBUTE, $token);
        if ($token->touch()) {
            $this->em->flush();
        }
        $user = $token->getUser();

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->unauthorized($request->headers->has('Authorization'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized(false);
    }

    private function unauthorized(bool $invalid): Response
    {
        $metadata = $this->urls->generate('oauth_protected_resource', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $challenge = \sprintf('Bearer resource_metadata="%s"', $metadata).($invalid ? ', error="invalid_token"' : '');

        return new JsonResponse(['error' => $invalid ? 'invalid_token' : 'unauthorized'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => $challenge]);
    }
}
