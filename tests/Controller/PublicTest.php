<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CalendarItem;
use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\EventSignup;
use App\Entity\MailAccount;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\PublicSettings;
use App\Entity\Survey;
use App\Entity\SurveyQuestion;
use App\Entity\User;
use App\Enum\Feature;
use App\Enum\OrganizationRole;
use App\Enum\SurveyQuestionType;
use App\Participation\PublicGuard;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Public participation: contact form, spam protection, surveys, event signups, subscriptions, settings.
 */
final class PublicTest extends AppTestCase
{
    private User $admin;
    private Organization $org;
    private MailAccount $account;
    private PublicSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('owner@example.org', 'Eva Owner');
        $this->org = $this->createOrganization($this->admin);
        $this->account = $this->createMailAccount($this->org);
        $this->settings = (new PublicSettings($this->org))->setSlug('sev-test')->setInfoEnabled(true)
            ->setContactEnabled(true)->setContactAccount($this->account)
            ->setSurveysEnabled(true)->setEventsEnabled(true)->setSubscribeEnabled(true);
        $this->em()->persist($this->settings);
        $this->em()->flush();
    }

    /**
     * Submits the form of the page with a render time far enough in the past.
     *
     * @param array<string, mixed> $values
     */
    private function submit(Crawler $crawler, string $button, array $values, ?int $started = null): Crawler
    {
        $form = $crawler->selectButton($button)->form();
        $data = array_replace_recursive($form->getPhpValues(), $values);
        $name = array_key_first($values);
        \assert(\is_string($name));
        $data[$name][PublicGuard::STAMP] = self::getContainer()->get(PublicGuard::class)->stamp($started ?? time() - 30);

        return $this->client->request('POST', $form->getUri(), $data);
    }

    /** @return array<string, mixed> */
    private function contactValues(string $honeypot = ''): array
    {
        return ['contact' => [
            'name' => 'Bert Beispiel', 'email' => 'bert@example.org', 'subject' => 'Frage zum Schulweg',
            'message' => 'Wie ist der Stand?', 'consent' => '1', PublicGuard::HONEYPOT => $honeypot,
        ]];
    }

    private function inboxCount(): int
    {
        return $this->em()->getRepository(Message::class)->count(['mailAccount' => $this->account]);
    }

    public function testContactFormLandsInInbox(): void
    {
        $crawler = $this->client->request('GET', '/p/sev-test/contact');
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Nachricht senden', $this->contactValues());

        self::assertSelectorTextContains('main', 'Deine Nachricht ist angekommen');
        $message = $this->em()->getRepository(Message::class)->findOneBy(['mailAccount' => $this->account]);
        self::assertNotNull($message);
        self::assertSame('Frage zum Schulweg', $message->getSubject());
        self::assertSame('bert@example.org', $message->getFromAddress());
    }

    public function testSpamProtectionRejectsHoneypotAndFastSubmissions(): void
    {
        $crawler = $this->client->request('GET', '/p/sev-test/contact');
        $this->submit($crawler, 'Nachricht senden', $this->contactValues('http://spam.example'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Die Eingabe konnte nicht verarbeitet werden');

        $crawler = $this->client->request('GET', '/p/sev-test/contact');
        $this->submit($crawler, 'Nachricht senden', $this->contactValues(), time());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Das ging sehr schnell');
        self::assertSame(0, $this->inboxCount());
    }

    public function testContactWithEmailConfirmation(): void
    {
        $this->settings->setSpamConfirmEmail(true);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/p/sev-test/contact');
        $this->submit($crawler, 'Nachricht senden', $this->contactValues());
        self::assertSelectorTextContains('main', 'Wir haben dir eine E-Mail geschickt');
        self::assertEmailCount(1);
        self::assertSame(0, $this->inboxCount());

        $crawler = $this->client->request('GET', $this->linkFromLastMail('/p/confirm/'));
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->inboxCount(), 'GET alone (mail scanners) must not confirm');
        $this->client->submit($crawler->selectButton('Bestätigen')->form());
        self::assertSelectorTextContains('main', 'Deine Nachricht ist angekommen');
        self::assertSame(1, $this->inboxCount());
    }

    public function testPartsFollowTheAreasOfTheOrganization(): void
    {
        $this->org->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::Mail !== $f));
        $this->em()->flush();

        $this->client->request('GET', '/p/sev-test/contact');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/p/sev-test');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('header a[href$="/contact"]');

        $settings = $this->em()->find(PublicSettings::class, $this->settings->getId());
        self::assertNotNull($settings);
        $settings->getOrganization()->setEnabledFeatures(array_filter(Feature::cases(), static fn (Feature $f): bool => Feature::PublicPage !== $f));
        $this->em()->flush();
        $this->client->request('GET', '/p/sev-test');
        self::assertResponseStatusCodeSame(404);
        self::assertTrue($settings->isContactEnabled(), 'switches stay stored');
    }

    public function testDisabledFeaturesAndUnknownPagesAreNotFound(): void
    {
        $this->settings->setContactEnabled(false);
        $this->em()->flush();

        $this->client->request('GET', '/p/sev-test/contact');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/p/unbekannt');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/p/sev-test');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('header', 'SEV Musterstadt');
    }

    public function testSurveyResponseAndCsvExport(): void
    {
        $survey = (new Survey($this->org, $this->admin))->setTitle('Schulweg')->setAnonymous(false);
        $survey->addQuestion($single = (new SurveyQuestion($survey))->setType(SurveyQuestionType::Single)->setLabel('Wie?')->setOptionsText("Zu Fuß\nRad")->setRequired(true));
        $survey->addQuestion($scale = (new SurveyQuestion($survey))->setType(SurveyQuestionType::Scale)->setLabel('Sicher?'));
        $this->em()->persist($survey);
        $this->em()->flush();

        $this->client->request('GET', '/p/sev-test/survey/'.$survey->getToken());
        self::assertResponseStatusCodeSame(404, 'drafts are not public');

        $survey->open();
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/p/sev-test/survey/'.$survey->getToken());
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Antworten senden', ['survey_answer' => [
            'q'.$single->getId() => 'Rad', 'q'.$scale->getId() => '4', 'name' => 'Bert', 'email' => 'bert@example.org', 'consent' => '1',
        ]]);
        self::assertSelectorTextContains('main', 'Danke für deine Teilnahme');

        $this->login($this->admin);
        $this->client->request('GET', '/surveys/'.$survey->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '1 Antwort');
        $this->client->request('GET', '/surveys/'.$survey->getId().'/export.csv');
        self::assertResponseIsSuccessful();
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('Bert', $csv);
        self::assertStringContainsString('Rad', $csv);
    }

    public function testEventSignupRespectsLimit(): void
    {
        $item = (new CalendarItem($this->admin))->setTitle('Elternabend')->setOrganization($this->org)
            ->setStartsAt(new \DateTimeImmutable('+7 days 19:00'))->setPublic(true)->setSignup(true)->setSignupLimit(3);
        $this->em()->persist($item);
        $this->em()->flush();
        $url = '/p/sev-test/event/'.$item->getId();

        $values = ['signup' => ['name' => 'Eva', 'email' => 'eva@example.org', 'persons' => '2', 'consent' => '1']];
        $this->submit($this->client->request('GET', $url), 'Anmelden', $values);
        self::assertSelectorTextContains('main', 'Du bist angemeldet');

        $values['signup']['email'] = 'bert@example.org';
        $this->submit($this->client->request('GET', $url), 'Anmelden', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'nicht mehr genug Plätze');
        self::assertSame(1, $this->em()->getRepository(EventSignup::class)->count(['item' => $item]));
    }

    public function testSubscribeWithDoubleOptInAndUnsubscribe(): void
    {
        $group = (new ContactGroup($this->org))->setName('Rundbrief')->setPublicSubscribe(true);
        $this->em()->persist($group);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/p/sev-test/subscribe');
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Eintragen', ['subscribe' => [
            'name' => 'Bert Beispiel', 'email' => 'bert@example.org', 'groups' => [(string) $group->getId()], 'consent' => '1',
        ]]);
        self::assertSelectorTextContains('main', 'Wir haben dir eine E-Mail geschickt');
        self::assertSame(0, $this->em()->getRepository(Contact::class)->count([]));

        $crawler = $this->client->request('GET', $this->linkFromLastMail('/p/confirm/'));
        $this->client->submit($crawler->selectButton('Bestätigen')->form());
        self::assertSelectorTextContains('main', 'Du bist eingetragen');
        $contact = $this->em()->getRepository(Contact::class)->findOneBy(['email' => 'bert@example.org']);
        self::assertNotNull($contact);
        self::assertSame([$group->getId()], $contact->getGroups()->map(static fn (ContactGroup $g): ?int => $g->getId())->getValues());

        $crawler = $this->client->request('GET', $this->linkFromLastMail('/p/sev-test/unsubscribe/'));
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Austragen')->form());
        self::assertSelectorTextContains('main', 'Du wurdest ausgetragen');
        $this->em()->clear();
        $contact = $this->em()->getRepository(Contact::class)->findOneBy(['email' => 'bert@example.org']);
        self::assertNotNull($contact);
        self::assertCount(0, $contact->getGroups());

        $this->client->request('GET', '/p/sev-test/unsubscribe/'.$group->getId().'?email=bert@example.org');
        self::assertResponseStatusCodeSame(404, 'unsigned links are rejected');
    }

    public function testSettingsOnlyForOrganizationAdmins(): void
    {
        $member = $this->createUser();
        $this->org->addMember($member, OrganizationRole::Member);
        $this->em()->flush();
        $url = '/organizations/'.$this->org->getId().'/public';

        $this->login($member);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);

        $this->login($this->admin);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Speichern')->form(['public_settings_form[slug]' => 'neu-name']));
        self::assertResponseRedirects();
        $this->client->request('GET', '/p/neu-name');
        self::assertResponseIsSuccessful();
    }
}
