<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agent\AgentScope;
use App\Entity\AgentToken;
use App\Entity\OAuthClient;
use App\Entity\OAuthCode;
use App\Entity\User;
use App\Enum\Feature;
use App\Repository\AgentTokenRepository;
use App\Repository\OrganizationRepository;
use App\Security\SecurityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * OAuth 2.1 for AI apps such as claude.ai: metadata (RFC 9728, RFC 8414), dynamic client registration (RFC 7591),
 * consent page and token endpoint (authorization code with PKCE, refresh tokens). Public clients only.
 */
final class OAuthController extends AbstractController
{
    private const int MAX_REDIRECT_URIS = 10;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/.well-known/oauth-protected-resource', name: 'oauth_protected_resource', methods: ['GET', 'OPTIONS'])]
    #[Route('/.well-known/oauth-protected-resource/mcp', name: 'oauth_protected_resource_mcp', methods: ['GET', 'OPTIONS'])]
    public function protectedResource(Request $request): Response
    {
        return self::respond([
            'resource' => self::resource($request),
            'authorization_servers' => [self::issuer($request)],
            'scopes_supported' => self::scopes(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Rokoso',
        ]);
    }

    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_metadata', methods: ['GET', 'OPTIONS'])]
    #[Route('/.well-known/openid-configuration', name: 'oauth_openid_metadata', methods: ['GET', 'OPTIONS'])]
    public function metadata(Request $request): Response
    {
        $issuer = self::issuer($request);

        return self::respond([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'registration_endpoint' => $issuer.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => self::scopes(),
        ]);
    }

    #[Route('/oauth/register', name: 'oauth_register', methods: ['POST', 'OPTIONS'])]
    public function register(
        Request $request,
        #[Autowire(service: 'limiter.oauth_register')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        if ($request->isMethod('OPTIONS')) {
            return self::respond(null, Response::HTTP_NO_CONTENT);
        }
        if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return self::respond(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        try {
            $data = json_decode($request->getContent(), true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }
        if (!\is_array($data)) {
            return self::oauthError('invalid_client_metadata', 'JSON body expected.');
        }
        $uris = $data['redirect_uris'] ?? null;
        if (!\is_array($uris) || [] === $uris || \count($uris) > self::MAX_REDIRECT_URIS) {
            return self::oauthError('invalid_redirect_uri', 'redirect_uris must list 1 to 10 URIs.');
        }
        foreach ($uris as $uri) {
            if (!\is_string($uri) || !self::isAllowedRedirect($uri)) {
                return self::oauthError('invalid_redirect_uri', 'Redirect URIs must use https (http only for localhost).');
            }
        }
        $name = \is_string($data['client_name'] ?? null) && '' !== trim($data['client_name']) ? $data['client_name'] : 'KI-Agent';
        $client = new OAuthClient($name, array_values(array_unique($uris)));
        $this->em->persist($client);
        $this->em->flush();

        return self::respond([
            'client_id' => $client->getClientId(),
            'client_id_issued_at' => $client->getCreatedAt()->getTimestamp(),
            'client_name' => $client->getName(),
            'redirect_uris' => $client->getRedirectUris(),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], Response::HTTP_CREATED);
    }

    #[Route('/oauth/authorize', name: 'oauth_authorize', methods: ['GET', 'POST'])]
    public function authorize(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations, SecurityLog $log): Response
    {
        $query = $request->query;
        $client = $this->em->getRepository(OAuthClient::class)->findOneBy(['clientId' => $query->getString('client_id')]);
        $redirectUri = $query->getString('redirect_uri');
        if (null === $client || !$client->hasRedirectUri($redirectUri)) {
            // never redirect to an unknown address
            return $this->render('oauth/error.html.twig', [], new Response(null, Response::HTTP_BAD_REQUEST));
        }
        $state = $query->has('state') ? $query->getString('state') : null;
        if ('code' !== $query->getString('response_type') || 'S256' !== $query->getString('code_challenge_method')
            || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $query->getString('code_challenge'))) {
            return self::redirectBack($redirectUri, ['error' => 'invalid_request', 'error_description' => 'PKCE with S256 is required.'], $state, $request);
        }
        if ($query->has('resource') && rtrim($query->getString('resource'), '/') !== self::resource($request)) {
            return self::redirectBack($redirectUri, ['error' => 'invalid_target'], $state, $request);
        }
        $requested = AgentScope::parse($query->getString('scope'));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('oauth_authorize', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if ($request->request->has('deny')) {
                return self::redirectBack($redirectUri, ['error' => 'access_denied'], $state, $request);
            }
            $chosen = array_values(array_filter(array_map(AgentScope::tryFrom(...), $request->request->all('scopes'))));
            if ([] !== $chosen) {
                $code = new OAuthCode($client, $user, $redirectUri, $query->getString('code_challenge'), $chosen);
                $secret = $code->issue();
                $this->em->persist($code);
                $this->em->flush();
                $log->record('agent_connected', $user, $client->getName());

                return self::redirectBack($redirectUri, ['code' => $secret], $state, $request);
            }
            $this->addFlash('error', 'agent.scopes_required');
        }

        return $this->render('oauth/authorize.html.twig', [
            'client' => $client,
            'scopes' => AgentScope::cases(),
            'requested' => $requested,
            'organizations' => array_values(array_filter($organizations->findForUser($user), static fn ($o): bool => $o->hasFeature(Feature::Agents))),
            'redirect_host' => parse_url($redirectUri, \PHP_URL_HOST),
        ]);
    }

    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST', 'OPTIONS'])]
    public function token(Request $request, AgentTokenRepository $tokens): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return self::respond(null, Response::HTTP_NO_CONTENT);
        }
        $payload = $request->getPayload();
        $clientId = $payload->getString('client_id');
        if ($payload->has('resource') && rtrim($payload->getString('resource'), '/') !== self::resource($request)) {
            return self::oauthError('invalid_target', 'Unknown resource.');
        }

        switch ($payload->getString('grant_type')) {
            case 'authorization_code':
                $code = $this->em->getRepository(OAuthCode::class)->findOneBy(['codeHash' => AgentToken::hash($payload->getString('code'))]);
                if (null === $code) {
                    return self::oauthError('invalid_grant', 'Unknown or used code.');
                }
                // single use, also when the exchange fails
                $this->em->remove($code);
                $this->em->flush();
                if ($code->isExpired() || $code->getClient()->getClientId() !== $clientId
                    || $code->getRedirectUri() !== $payload->getString('redirect_uri') || !$code->verifies($payload->getString('code_verifier'))) {
                    return self::oauthError('invalid_grant', 'Code, client, redirect URI or verifier do not match.');
                }
                $token = new AgentToken($code->getUser(), $code->getClient()->getName(), $code->getScopes(), $code->getClient());
                $this->em->persist($token);
                break;
            case 'refresh_token':
                $token = $tokens->findByRefreshToken($payload->getString('refresh_token'));
                if (null === $token || !$token->canRefresh() || $token->getClient()?->getClientId() !== $clientId) {
                    return self::oauthError('invalid_grant', 'Refresh token is invalid or expired.');
                }
                break;
            default:
                return self::oauthError('unsupported_grant_type', 'Only authorization_code and refresh_token are supported.');
        }
        $secrets = $token->issueOAuth();
        $this->em->flush();

        return self::respond([
            'access_token' => $secrets['access'],
            'token_type' => 'Bearer',
            'expires_in' => AgentToken::ACCESS_LIFETIME,
            'refresh_token' => $secrets['refresh'],
            'scope' => AgentScope::join($token->getScopes()),
        ]);
    }

    private static function issuer(Request $request): string
    {
        return $request->getSchemeAndHttpHost().$request->getBasePath();
    }

    private static function resource(Request $request): string
    {
        return self::issuer($request).'/mcp';
    }

    /**
     * @return list<string>
     */
    private static function scopes(): array
    {
        return array_map(static fn (AgentScope $s): string => $s->value, AgentScope::cases());
    }

    private static function isAllowedRedirect(string $uri): bool
    {
        if (\strlen($uri) > 2000 || false === filter_var($uri, \FILTER_VALIDATE_URL) || str_contains($uri, '#')) {
            return false;
        }
        $scheme = strtolower((string) parse_url($uri, \PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($uri, \PHP_URL_HOST));

        return 'https' === $scheme || ('http' === $scheme && \in_array($host, ['localhost', '127.0.0.1', '[::1]'], true));
    }

    /**
     * @param array<string, string> $params
     */
    private static function redirectBack(string $uri, array $params, ?string $state, Request $request): RedirectResponse
    {
        $params += array_filter(['state' => $state, 'iss' => self::issuer($request)], static fn ($v): bool => null !== $v);

        return new RedirectResponse($uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($params));
    }

    private static function oauthError(string $error, string $description): JsonResponse
    {
        return self::respond(['error' => $error, 'error_description' => $description], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Machine answers: not cached, readable from other origins (no cookies are used).
     *
     * @param array<string, mixed>|null $data
     */
    private static function respond(?array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse(null, $status, [
            'Cache-Control' => 'no-store',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, MCP-Protocol-Version',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        ]);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return null === $data ? $response->setContent('') : $response->setData($data);
    }
}
