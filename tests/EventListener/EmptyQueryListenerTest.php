<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Message;
use App\Entity\Project;
use App\Tests\AppTestCase;

final class EmptyQueryListenerTest extends AppTestCase
{
    /**
     * The "all" option of a filter select is an empty value; getInt() would answer it with 400.
     */
    public function testFilterPagesAcceptEmptyValues(): void
    {
        $user = $this->createUser();
        $this->createOrganization($user);
        $this->client->loginUser($user);

        foreach (['/mail?q=&show=all&project=&account=&page=', '/tasks?project=', '/resolutions?year=&project=&organization=', '/calendar?project=', '/contacts?group=', '/contacts/facilities?status=&district='] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    /**
     * Regression: with a project the mail search form sends project= along – the search ran into a 400
     * and Turbo reloaded the unfiltered page.
     */
    public function testMailSearchFormWithProjectSelect(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user);
        $account = $this->createMailAccount($organization);
        $this->em()->persist((new Project($user))->setName('Schulwegsicherheit')->setOrganization($organization));
        foreach (['Treffen' => 'Wir planen den Laternenumzug.', 'Elternabend' => 'Hallo'] as $subject => $body) {
            $this->em()->persist((new Message())->setMailAccount($account)->setFrom('eva@example.org', 'Eva')->setSubject($subject)->setBody($body));
        }
        $this->em()->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mail');
        self::assertCount(1, $crawler->filter('#mail-project'));
        $form = $crawler->filter('form[role=search]')->form(['q' => 'Laternenumzug']);
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Treffen');
        self::assertSelectorTextNotContains('main', 'Elternabend');
    }
}
