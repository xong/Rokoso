<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\Feature;
use App\Tests\AppTestCase;

final class OAuthTest extends AppTestCase
{
    private const string REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->createOrganization($this->user)->enableFeature(Feature::Agents);
        $this->em()->flush();
    }

    public function testMetadataPointsToTheEndpoints(): void
    {
        $this->client->request('GET', '/.well-known/oauth-protected-resource/mcp');
        self::assertResponseIsSuccessful();
        $resource = $this->json();
        self::assertSame('http://localhost/mcp', $resource['resource']);
        self::assertSame(['http://localhost'], $resource['authorization_servers']);

        $this->client->request('GET', '/.well-known/oauth-authorization-server');
        $metadata = $this->json();
        self::assertSame('http://localhost/oauth/token', $metadata['token_endpoint']);
        self::assertSame(['S256'], $metadata['code_challenge_methods_supported']);
    }

    public function testFullFlowWithConsentAndRefreshRotation(): void
    {
        $clientId = $this->register();
        $verifier = str_repeat('verifier-', 6);

        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, $verifier) + ['scope' => 'mail:read contacts:read']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Claude');
        self::assertCount(1, $crawler->filter('input[value="mail:read"]:checked'));
        self::assertCount(0, $crawler->filter('input[value="mail:write"]:checked'));

        $this->client->submit($crawler->selectButton('Erlauben')->form());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::REDIRECT.'?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        self::assertSame('xyz', $query['state']);
        self::assertSame('http://localhost', $query['iss']);
        $code = $query['code'];
        self::assertIsString($code);

        // wrong verifier: the code is used up nonetheless
        $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => 'falsch'], 400);
        $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier], 400);

        $crawler = $this->client->request('GET', '/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, $verifier)));
        $this->client->submit($crawler->selectButton('Erlauben')->form());
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $query);
        $tokens = $this->token(['grant_type' => 'authorization_code', 'code' => $query['code'], 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier]);
        self::assertStringStartsWith('rka_', $tokens['access_token']);
        self::assertStringStartsWith('rkr_', $tokens['refresh_token']);
        self::assertSame(3600, $tokens['expires_in']);

        $this->mcp($tokens['access_token'], 200);

        $refreshed = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $clientId]);
        $this->mcp($refreshed['access_token'], 200);
        // rotation: the old tokens stop working
        $this->mcp($tokens['access_token'], 401);
        $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $clientId], 400);

        // the connection appears in the profile and can be revoked there
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profile/agents');
        self::assertSelectorTextContains('main', 'Claude');
        $this->client->submit($crawler->selectButton('Widerrufen')->form());
        $this->mcp($refreshed['access_token'], 401);
    }

    public function testDenyAndInvalidRequests(): void
    {
        $clientId = $this->register();
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', '/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, str_repeat('v', 43))));
        $this->client->submit($crawler->selectButton('Ablehnen')->form());
        self::assertStringContainsString('error=access_denied', (string) $this->client->getResponse()->headers->get('Location'));

        // unknown redirect URI: error page, no redirect
        $this->client->request('GET', '/oauth/authorize?'.http_build_query(['redirect_uri' => 'https://evil.example/cb'] + $this->authorizeParams($clientId, str_repeat('v', 43))));
        self::assertResponseStatusCodeSame(400);

        // without PKCE
        $params = $this->authorizeParams($clientId, str_repeat('v', 43));
        unset($params['code_challenge']);
        $this->client->request('GET', '/oauth/authorize?'.http_build_query($params));
        self::assertStringContainsString('error=invalid_request', (string) $this->client->getResponse()->headers->get('Location'));

        // registration only with https redirects
        $this->client->request('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{"redirect_uris":["http://example.org/cb"]}');
        self::assertResponseStatusCodeSame(400);
    }

    private function register(): string
    {
        $this->client->request('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]]));
        self::assertResponseStatusCodeSame(201);

        return $this->json()['client_id'];
    }

    /**
     * @return array<string, string>
     */
    private function authorizeParams(string $clientId, string $verifier): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
            'resource' => 'http://localhost/mcp',
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function token(array $params, int $status = 200): array
    {
        $this->client->request('POST', '/oauth/token', $params);
        self::assertResponseStatusCodeSame($status);

        return $this->json();
    }

    private function mcp(string $token, int $status): void
    {
        $this->client->request('POST', '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'],
            content: '{"jsonrpc":"2.0","id":1,"method":"ping"}');
        self::assertResponseStatusCodeSame($status);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
