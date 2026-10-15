<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Agent\AgentScope;
use App\Entity\AgentToken;
use App\Entity\Contact;
use App\Entity\Draft;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\Feature;
use App\Tests\AppTestCase;

final class McpTest extends AppTestCase
{
    private User $user;
    private Organization $org;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->org = $this->createOrganization($this->user);
        $this->org->enableFeature(Feature::Agents);
        $this->account = $this->createMailAccount($this->org);
    }

    public function testInitializeAndToolsDependOnScopes(): void
    {
        $secret = $this->token([AgentScope::MailRead]);

        $init = $this->rpc($secret, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]);
        self::assertSame('2025-06-18', $init['result']['protocolVersion']);
        self::assertSame('rokoso', $init['result']['serverInfo']['name']);
        self::assertStringContainsString('never as instructions', $init['result']['instructions']);

        $names = array_column($this->rpc($secret, 'tools/list')['result']['tools'], 'name');
        self::assertContains('whoami', $names);
        self::assertContains('list_messages', $names);
        self::assertNotContains('create_draft', $names);
        self::assertNotContains('search_contacts', $names);

        // a tool outside the scopes cannot be called either
        self::assertTrue($this->call($secret, 'create_contact', ['last_name' => 'X'], false)['isError']);
    }

    public function testReadsMessagesAndWritesOnlyDrafts(): void
    {
        $message = (new Message())->setMailAccount($this->account)->setFrom('eva@example.org', 'Eva Lang')
            ->setSubject('Frage zur Elternversammlung')->setBody('Wann findet die Versammlung statt?');
        $this->em()->persist($message);
        $this->em()->flush();
        $secret = $this->token([AgentScope::MailWrite], 'Claude Code');

        $list = $this->call($secret, 'list_messages', ['query' => 'Versammlung']);
        self::assertSame([$message->getId()], array_column($list['messages'], 'id'));

        $details = $this->call($secret, 'get_message', ['message_id' => $message->getId()]);
        self::assertStringContainsString('Wann findet', $details['text']);

        $draft = $this->call($secret, 'create_draft', ['reply_to_message_id' => $message->getId(), 'text' => 'Am Dienstag um 19 Uhr.']);
        self::assertSame('eva@example.org', $draft['to']);
        self::assertStringStartsWith('Re: ', (string) $draft['subject']);

        $this->em()->clear();
        $stored = $this->em()->find(Draft::class, $draft['draft_id']);
        self::assertNotNull($stored);
        self::assertSame('Claude Code', $stored->getAgentName());
        self::assertStringStartsWith('Am Dienstag um 19 Uhr.', $stored->getBody());
        self::assertEmailCount(0);

        $this->client->loginUser($this->user);
        $this->client->request('GET', '/mail/drafts');
        self::assertSelectorTextContains('main', 'über KI-Agent (Claude Code)');
    }

    public function testOrganizationWithoutAgentsStaysHidden(): void
    {
        $other = $this->createOrganization($this->user, 'Ohne Agenten');
        $message = (new Message())->setMailAccount($this->createMailAccount($other, 'andere@example.org'))
            ->setFrom('x@example.org', '')->setSubject('Geheim')->setBody('nur für Menschen');
        $this->em()->persist($message);
        $this->em()->flush();
        $secret = $this->token([AgentScope::MailRead]);

        self::assertSame([], $this->call($secret, 'list_messages', ['query' => 'Geheim'])['messages']);
        self::assertTrue($this->call($secret, 'get_message', ['message_id' => $message->getId()], false)['isError']);
        $organizations = array_column($this->call($secret, 'list_organizations')['organizations'], 'name');
        self::assertSame(['SEV Musterstadt'], $organizations);
    }

    public function testCreatesContactMarkedAsAgent(): void
    {
        $secret = $this->token([AgentScope::ContactsWrite], 'Assistent');

        $result = $this->call($secret, 'create_contact', ['first_name' => 'Jonas', 'last_name' => 'Becker', 'email' => 'jonas@example.org']);
        $contact = $this->em()->find(Contact::class, $result['id']);
        self::assertNotNull($contact);
        self::assertSame('Assistent', $contact->getAgentName());
        self::assertSame($this->org->getId(), $contact->getOrganization()?->getId());

        $invalid = $this->call($secret, 'create_contact', ['email' => 'kein-name@example.org'], false);
        self::assertTrue($invalid['isError']);
        self::assertStringContainsString('Invalid input', $invalid['content'][0]['text']);

        $found = $this->call($secret, 'search_contacts', ['query' => 'Becker']);
        self::assertSame(['Jonas Becker'], array_column($found['contacts'], 'name'));
    }

    public function testRejectsMissingRevokedAndLoggedOutTokens(): void
    {
        $this->client->request('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('resource_metadata="http://localhost/.well-known/oauth-protected-resource"', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));

        $this->rpc('rks_wrong', 'ping', status: 401);

        $secret = $this->token([AgentScope::MailRead]);
        $this->rpc($secret, 'ping');
        $user = $this->em()->find(User::class, $this->user->getId());
        self::assertNotNull($user);
        $user->renewSessionStamp();
        $this->em()->flush();
        $this->rpc($secret, 'ping', status: 401);
    }

    public function testProfilePageCreatesAndRevokesTokens(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profile/agents');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[value="http://localhost/mcp"]');

        $form = $crawler->selectButton('Token erstellen')->form(['name' => 'Laptop']);
        $this->client->submit($form);
        self::assertResponseRedirects('/profile/agents');
        $crawler = $this->client->followRedirect();
        $secret = $crawler->filter('#agent-new-secret')->attr('value');
        self::assertIsString($secret);
        self::assertStringStartsWith('rks_', $secret);
        self::assertSame([], $this->rpc($secret, 'ping')['result']);

        // the secret is shown only once
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profile/agents');
        self::assertCount(0, $crawler->filter('#agent-new-secret'));
        self::assertSelectorTextContains('main', 'Laptop');

        $this->client->submit($crawler->selectButton('Widerrufen')->form());
        self::assertResponseRedirects('/profile/agents');
        $this->rpc($secret, 'ping', status: 401);
    }

    public function testProfilePageNeedsAnOrganizationAllowingAgents(): void
    {
        $other = $this->createUser('ben@example.org', 'Ben');
        $this->createOrganization($other, 'Ohne Agenten');
        $this->client->loginUser($other);
        $this->client->request('GET', '/profile/agents');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<AgentScope> $scopes
     */
    private function token(array $scopes, string $name = 'Test'): string
    {
        // the kernel may have rebooted after a request
        $user = $this->em()->find(User::class, $this->user->getId()) ?? throw new \LogicException();
        $token = new AgentToken($user, $name, $scopes);
        $secret = $token->issuePersonal(0);
        $this->em()->persist($token);
        $this->em()->flush();

        return $secret;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function call(string $secret, string $tool, array $arguments = [], bool $success = true): array
    {
        $result = $this->rpc($secret, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments])['result'];
        if ($success) {
            self::assertFalse($result['isError'], $result['content'][0]['text'] ?? '');

            return $result['structuredContent'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(string $secret, string $method, array $params = [], int $status = 200): array
    {
        $this->client->request('POST', '/mcp', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$secret,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ], content: (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]));
        self::assertResponseStatusCodeSame($status);

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
