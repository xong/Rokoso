<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use App\Entity\Invitation;
use App\Enum\OrganizationRole;

/**
 * Open invitations offer their link for copying (e.g. when the email could not be sent).
 */
final class InvitationTest extends BrowserTestCase
{
    public function testCopyLinkConfirms(): void
    {
        $owner = $this->createUser();
        $org = $this->createOrganization($owner);
        $invitation = new Invitation($org, 'invite-'.bin2hex(random_bytes(4)).'@example.org', OrganizationRole::Member, $owner);
        $this->em()->persist($invitation);
        $this->em()->flush();

        $this->login($owner);
        $this->client->request('GET', '/organizations/'.$org->getId());
        $this->client->waitFor('[data-controller="copy"] button');
        $this->client->executeScript('document.querySelector("[data-controller=copy] button").click();');
        $this->client->waitForElementToContain('[data-copy-target="label"]', 'Link kopiert');

        self::assertSame('Link kopiert', $this->client->executeScript('return document.querySelector("[data-copy-target=status]").textContent;'));
    }
}
