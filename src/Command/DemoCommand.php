<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AgendaItem;
use App\Entity\Attendance;
use App\Entity\CalendarItem;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\ContactGroup;
use App\Entity\Draft;
use App\Entity\EventSignup;
use App\Entity\FileShare;
use App\Entity\Folder;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\MailAccount;
use App\Entity\MailRule;
use App\Entity\Meeting;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Poll;
use App\Entity\PollAnswer;
use App\Entity\PollBallot;
use App\Entity\PollOption;
use App\Entity\Project;
use App\Entity\PublicSettings;
use App\Entity\PublicTopic;
use App\Entity\Resolution;
use App\Entity\ShelfItem;
use App\Entity\Signature;
use App\Entity\StoredFile;
use App\Entity\Survey;
use App\Entity\SurveyQuestion;
use App\Entity\SurveyResponse;
use App\Entity\TextSnippet;
use App\Entity\User;
use App\Entity\Watch;
use App\Entity\WikiPage;
use App\Enum\AttendanceStatus;
use App\Enum\CalendarItemType;
use App\Enum\MailEncryption;
use App\Enum\MailRuleField;
use App\Enum\MessageType;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Enum\PollKind;
use App\Enum\Recurrence;
use App\Enum\SurveyQuestionType;
use App\Enum\TaskStatus;
use App\Mail\ComposeData;
use App\Mail\MailSynchronizer;
use App\Meeting\MeetingService;
use App\Repository\UserRepository;
use App\Service\FileStorage;
use App\Service\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Local demo data: user demo@koopio.test (password "demo-passwort"), organization, projects and a mail
 * account on the GreenMail container from compose.yaml. Optionally sends sample mails into the mailbox.
 */
#[AsCommand(name: 'app:demo', description: 'Demodaten für die lokale Entwicklung anlegen')]
final readonly class DemoCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private SecretBox $secretBox,
        private MailSynchronizer $synchronizer,
        private UrlGeneratorInterface $urls,
        private MeetingService $meetings,
        private FileStorage $files,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Beispiel-E-Mails an das GreenMail-Postfach schicken')] bool $mails = false): int
    {
        $user = $this->users->findOneByEmail('demo@koopio.test');
        if (null === $user) {
            $user = (new User())->setEmail('demo@koopio.test')->setName('Dana Demo')->setVerified(true)->setPlatformAdmin(true);
            $user->setPassword($this->hasher->hashPassword($user, 'demo-passwort'));
            $user->resetCalendarToken();
            $this->em->persist($user);

            $colleague = (new User())->setEmail('kim@koopio.test')->setName('Kim Kollegin')->setVerified(true);
            $colleague->setPassword($this->hasher->hashPassword($colleague, 'demo-passwort'));
            $this->em->persist($colleague);

            $org = (new Organization())->setName('SEV Musterstadt')->setColor('#2563eb')
                ->setDescription('Stadtelternvertretung der Musterstädter Schulen');
            $org->addMember($user, OrganizationRole::Admin)->setPosition('Vorsitz');
            $org->addMember($colleague, OrganizationRole::Member)->setPosition('Schriftführung')
                ->setTermEndsOn(new \DateTimeImmutable('31 July next year'));
            $guest = (new User())->setEmail('gast@koopio.test')->setName('Gerd Gastmitglied')->setVerified(true);
            $guest->setPassword($this->hasher->hashPassword($guest, 'demo-passwort'));
            $this->em->persist($guest);
            $org->addMember($guest, OrganizationRole::Member)->setVotingRight(false);
            $this->em->persist($org);

            $projects = [];
            foreach (['Schulwegsicherheit' => '#16a34a', 'Elternabend Herbst' => '#ea580c'] as $name => $color) {
                $projects[] = $project = (new Project($user))->setName($name)->setColor($color)->setOrganization($org);
                $this->em->persist($project);
            }
            $projects[0]->setLead($colleague);
            $projects[1]->setLead($user);
            $this->em->persist((new Project($user))->setName('Sommerfest 2025')->setColor('#a855f7')->setOrganization($org)->archive());

            // external guest: sees only the released project
            $school = (new User())->setEmail('schule@koopio.test')->setName('Sabine Schulleitung')->setVerified(true);
            $school->setPassword($this->hasher->hashPassword($school, 'demo-passwort'));
            $this->em->persist($school);
            $org->addMember($school, OrganizationRole::Guest)->setVotingRight(false)->setPosition('Schulleitung GS Nord')->addGuestProject($projects[0]);
            $this->createWiki($org, $user, $colleague);

            $principals = (new ContactGroup($org))->setName('Schulleitungen')->setDescription('Alle Schulleitungen im Stadtgebiet');
            $councils = (new ContactGroup($org))->setName('Elternbeiräte')->setDescription('Vorsitzende der Schulelternbeiräte');
            $this->em->persist($principals);
            $this->em->persist($councils);
            $contacts = [];
            foreach ([
                ['Eva', 'Elternteil', 'GS Nord', 'Vorsitzende Elternbeirat', 'eva@example.org', $councils],
                ['Jonas', 'Becker', 'Gymnasium am Park', 'Vorsitzender Elternbeirat', 'jonas.becker@example.org', $councils],
                ['Petra', 'Lang', 'GS Nord', 'Schulleiterin', 'lang@gs-nord.example.org', $principals],
                ['Uwe', 'Krämer', 'Gymnasium am Park', 'Schulleiter', 'kraemer@gymnasium.example.org', $principals],
                ['Aylin', 'Demir', 'Gesamtschule Süd', 'Schulleiterin', 'demir@gesamtschule.example.org', $principals],
                [null, null, 'Schulamt Musterstadt', null, 'info@schulamt.example.org', null],
            ] as [$first, $last, $company, $position, $email, $group]) {
                $contacts[] = $contact = (new Contact($user))->setFirstName($first)->setLastName($last)->setCompany($company)
                    ->setPosition($position)->setEmail($email)->setOrganization($org);
                $group?->addContact($contact);
                $this->em->persist($contact);
            }
            $this->em->persist(Comment::onContact($contacts[2], $colleague)->setBody('Bevorzugt Anrufe vormittags, E-Mails beantwortet sie meist erst am Wochenende.'));

            $monday = new \DateTimeImmutable('monday this week');
            $board = (new CalendarItem($user))->setTitle('Vorstandstreffen')->setOrganization($org)->setLocation('Rathaus, Raum 2')
                ->setStartsAt($monday->setTime(19, 0))->setEndsAt($monday->setTime(21, 0))
                ->setRecurrence(Recurrence::Weekly)->setRecurrenceInterval(2)->addParticipant($user)->addParticipant($colleague);
            // One meeting moved to Tuesday at another place, one cancelled (holidays)
            $board->exceptionFor($monday->modify('+14 days'))->setStartsAt($monday->modify('+15 days')->setTime(18, 30))
                ->setEndsAt($monday->modify('+15 days')->setTime(20, 30))->setLocation('Schule am Park, Lehrerzimmer');
            $board->exceptionFor($monday->modify('+42 days'))->setCancelled(true);
            $this->em->persist($board);
            $parentsEvening = (new CalendarItem($user))->setTitle('Elternabend')->setOrganization($org)->setProject($projects[1])
                ->setStartsAt($monday->modify('+9 days')->setTime(19, 30))->setEndsAt($monday->modify('+9 days')->setTime(21, 0))->setLocation('Aula')
                ->setDescription("Infoabend für alle Eltern der Stadt.\nThemen: Schulwege, Ganztag, Elternbeiräte.")
                ->setPublic(true)->setSignup(true)->setSignupLimit(80)->setReminderMinutes(2880)
                ->setGuestEmails("schulleitung@schule-am-park.example\npresse@musterstadt.example");
            $this->em->persist($parentsEvening);
            $this->em->persist(new EventSignup($parentsEvening, 'Eva Elternteil', 'eva@example.org', 2));
            $councils->setPublicSubscribe(true);
            $this->em->persist((new CalendarItem($user))->setTitle('Protokoll verschicken')->setType(CalendarItemType::Task)->setOrganization($org)
                ->setStartsAt($monday->modify('+3 days')->setTime(12, 0))->addAssignee($colleague));

            $note = (new Message(MessageType::Internal))->setAuthor($colleague)->setFrom($colleague->getEmail(), $colleague->getName())
                ->addRecipientUser($user)->setSubject('Willkommen in Koopio')
                ->setBody("Hallo,\n\nhier können wir Mails gemeinsam bearbeiten, kommentieren und Verantwortliche festlegen. Für Absprachen in der Gruppe nutzen wir das Forum.\n\nKim");
            $this->em->persist($note);

            $minutes = (new Folder($org, $user))->setName('Protokolle');
            $this->em->persist($minutes);
            $this->em->persist((new Folder($org, $user, $minutes))->setName('2026'));
            $this->em->persist((new Folder($org, $user))->setName('Schulwege')->setProject($projects[0]));

            $general = (new ForumBoard($org, $user))->setName('Allgemeines')->setDescription('Alles, was nicht in einen anderen Bereich passt');
            $this->em->persist($general);
            $this->em->persist((new ForumBoard($org, $user))->setName('Schulwege')->setProject($projects[0]));
            $topic = (new ForumTopic($general, $colleague))->setTitle('Termine für das neue Schuljahr');
            $topic->addPost((new ForumPost($topic, $colleague))->setBody("Hallo zusammen,\n\nwelche Termine stehen schon fest?\n\n- Gesamtelternbeirat\n- **Elternabend** im Herbst\n\nKim"));
            $topic->addPost((new ForumPost($topic, $user))->setBody('Der Elternabend ist eingetragen, siehe *Kalender*.'));
            $this->em->persist($topic);
            $this->em->persist((new CalendarItem($user))->setTitle('Sitzungsraum für den Herbst anfragen')->setType(CalendarItemType::Task)
                ->setOrganization($org)->setStartsAt(null)->setStatus(TaskStatus::InProgress)->addAssignee($user)->setSourceTopic($topic));
            $this->em->persist((new CalendarItem($user))->setTitle('Gefahrenstellen sammeln')->setType(CalendarItemType::Task)
                ->setOrganization($org)->setProject($projects[0])->setStartsAt(null)->addAssignee($user)->addAssignee($colleague));
            $this->em->persist((new CalendarItem($user))->setTitle('Einladung Elternabend verschicken')->setType(CalendarItemType::Task)
                ->setOrganization($org)->setProject($projects[1])->setStartsAt($monday->modify('+2 days')->setTime(18, 0))->addAssignee($user));
            $this->em->persist((new CalendarItem($colleague))->setTitle('Raum für den Elternabend buchen')->setType(CalendarItemType::Task)
                ->setOrganization($org)->setProject($projects[1])->setStartsAt($monday->modify('-2 days')->setTime(12, 0))->setStatus(TaskStatus::Done));
            $this->createMeetings($org, $user, $colleague, $guest, $projects);
            $this->createPolls($org, $user, $colleague, $topic, $projects);
            $this->createFiles($minutes, $user, $colleague, $note, $topic);
            $this->em->persist(new Watch($user, $general));
            $this->em->persist(new Watch($user, $projects[0]));
            $this->em->persist(new Watch($colleague, $topic));
            $this->em->flush();
            $topicUrl = $this->urls->generate('forum_topic_show', ['id' => $topic->getId()]);
            $this->em->persist(new Notification($user, NotificationType::Post, $topic->getTitle(), $topicUrl, $colleague));
            $this->em->persist(new Notification($colleague, NotificationType::Mentioned, $topic->getTitle(), $topicUrl, $user));

            $account = (new MailAccount($org))
                ->setName('Postfach SEV')
                ->setEmailAddress('sev@koopio.test')
                ->setSenderName('SEV Musterstadt')
                ->setImapHost('127.0.0.1')->setImapPort(3143)->setImapEncryption(MailEncryption::None)
                ->setImapUsername('sev@koopio.test')->setImapPassword($this->secretBox->encrypt('demo'))
                ->setSmtpHost('127.0.0.1')->setSmtpPort(3025)->setSmtpEncryption(MailEncryption::None);
            $this->em->persist($account);
            $this->em->persist((new MailRule($org))->setName('Rundbrief Landeselternrat')->setField(MailRuleField::From)
                ->setNeedle('newsletter@')->setMarkDone(true));
            $this->createWritingAids($org, $account, $user, $colleague);
            $this->createPublicPage($org, $account, $user, $colleague, $projects);
            $this->em->flush();
            $io->success('Demo angelegt: demo@koopio.test / demo-passwort (und kim@koopio.test, Gast schule@koopio.test)');
        }

        if ($mails) {
            $transport = Transport::fromDsn('smtp://127.0.0.1:3025?auto_tls=false');
            $samples = [
                ['Eva Elternteil <eva@example.org>', 'Zebrastreifen an der Grundschule Nord', "Hallo zusammen,\n\nwie ist der Stand beim Zebrastreifen? Die Kinder müssen dort jeden Morgen über die Straße.\n\nViele Grüße\nEva"],
                ['Schulamt Musterstadt <info@schulamt.example.org>', 'Einladung: Gesamtelternbeirat am 15.10.', "Sehr geehrte Damen und Herren,\n\nanbei die Einladung und Tagesordnung.\n\nMit freundlichen Grüßen\nSchulamt"],
                ['Bert Beispiel <bert@example.org>', 'Elternabend Herbst', "Wer kümmert sich um den Raum?\n\nBert", 'elternabend@example.org'],
                ['Eva Elternteil <eva@example.org>', 'Re: Elternabend Herbst', "Ich kann die Aula organisieren.\n\nEva", null, 'elternabend@example.org'],
                ['Landeselternrat <newsletter@ler.example.org>', 'Rundbrief Oktober', "Liebe Elternvertretungen,\n\nhier unsere Neuigkeiten.\n\nLandeselternrat"],
            ];
            foreach ($samples as $sample) {
                [$from, $subject, $text] = $sample;
                $email = (new Email())->from($from)->to('sev@koopio.test')->subject($subject)->text($text)
                    ->html('<p>'.nl2br(htmlspecialchars($text)).'</p>');
                if (isset($sample[3])) {
                    $email->getHeaders()->addIdHeader('Message-ID', $sample[3]);
                }
                if (isset($sample[4])) {
                    $email->getHeaders()->addIdHeader('In-Reply-To', $sample[4]);
                    $email->getHeaders()->addIdHeader('References', $sample[4]);
                }
                if (str_contains($subject, 'Einladung')) {
                    $email->attach("Tagesordnung\n1. Begrüßung\n2. Schulwege\n", 'tagesordnung.txt', 'text/plain');
                }
                $transport->send($email);
            }
            $io->writeln('Beispiel-E-Mails verschickt.');
        }

        foreach ($this->em->getRepository(MailAccount::class)->findBy(['emailAddress' => 'sev@koopio.test']) as $account) {
            $io->writeln(\sprintf('Abruf: %d neue E-Mail(s)%s', $this->synchronizer->sync($account), $account->getLastSyncError() ? ' – Fehler: '.$account->getLastSyncError() : ''));
            // Kim is answering the question about the zebra crossing: the hint "is writing" shows up for Dana
            $question = $this->em->getRepository(Message::class)->findOneBy(['mailAccount' => $account, 'subject' => 'Zebrastreifen an der Grundschule Nord']);
            $kim = $this->users->findOneByEmail('kim@koopio.test');
            if (null !== $question && null !== $kim && 0 === $this->em->getRepository(Draft::class)->count(['original' => $question])) {
                $reply = new ComposeData();
                $reply->account = $account;
                $reply->original = $question;
                $reply->to = $question->getFromAddress();
                $reply->subject = 'Re: '.$question->getSubject();
                $reply->body = "Hallo Eva,\n\nwir haben das Thema beim Ordnungsamt angesprochen …";
                $this->em->persist((new Draft($kim, $account))->apply($reply));
                $this->em->flush();
            }
        }

        return 0;
    }

    /**
     * Public page with contact form, a topic and a running survey with a few answers.
     *
     * @param list<Project> $projects
     */
    private function createPublicPage(Organization $org, MailAccount $account, User $user, User $colleague, array $projects): void
    {
        $settings = (new PublicSettings($org))->setSlug('sev-musterstadt')->setInfoEnabled(true)
            ->setIntro("Wir sind die **Stadtelternvertretung Musterstadt** und vertreten die Eltern aller Schulen und Kitas.\n\nSchreib uns, mach bei Umfragen mit oder komm zu unseren Veranstaltungen.")
            ->setContactEnabled(true)->setContactAccount($account)->setTopicsEnabled(true)
            ->setSurveysEnabled(true)->setEventsEnabled(true)->setSubscribeEnabled(true);
        $settings->addTopic((new PublicTopic($settings))->setName('Schulweg')->setProject($projects[0])->setAssignee($colleague));
        $settings->addTopic((new PublicTopic($settings))->setName('Veranstaltungen')->setProject($projects[1])->setAssignee($user));
        $settings->addTopic((new PublicTopic($settings))->setName('Sonstiges'));
        $this->em->persist($settings);

        $survey = (new Survey($org, $user))->setTitle('Wie kommt Ihr Kind zur Schule?')
            ->setDescription('Eine kurze Umfrage zur Schulwegsicherheit. Dauert keine zwei Minuten.')->setAnonymous(true)->setListed(true);
        $questions = [
            (new SurveyQuestion($survey))->setType(SurveyQuestionType::Single)->setLabel('Wie kommt Ihr Kind meistens zur Schule?')
                ->setOptionsText("Zu Fuß\nFahrrad\nBus/Bahn\nAuto")->setRequired(true),
            (new SurveyQuestion($survey))->setType(SurveyQuestionType::Multiple)->setLabel('Wo sehen Sie Gefahrenstellen?')
                ->setOptionsText("Kreuzungen\nFehlende Zebrastreifen\nElterntaxis\nBaustellen"),
            (new SurveyQuestion($survey))->setType(SurveyQuestionType::Scale)->setLabel('Wie sicher ist der Schulweg insgesamt? (1 = unsicher, 5 = sehr sicher)')->setRequired(true),
            (new SurveyQuestion($survey))->setType(SurveyQuestionType::Text)->setLabel('Was sollten wir ansprechen?'),
        ];
        foreach ($questions as $question) {
            $survey->addQuestion($question);
        }
        $survey->open();
        $this->em->persist($survey);
        $this->em->flush();

        [$way, $danger, $scale, $text] = array_map(static fn (SurveyQuestion $q): int => (int) $q->getId(), $questions);
        foreach ([
            ['Zu Fuß', ['Kreuzungen', 'Elterntaxis'], 2, 'Die Ampel an der Hauptstraße ist viel zu kurz grün.'],
            ['Fahrrad', ['Baustellen'], 3, ''],
            ['Auto', ['Elterntaxis'], 4, ''],
            ['Zu Fuß', ['Fehlende Zebrastreifen', 'Kreuzungen'], 2, 'Bitte einen Zebrastreifen an der Grundschule Nord!'],
        ] as [$a, $b, $c, $d]) {
            $this->em->persist(new SurveyResponse($survey, [$way => $a, $danger => $b, $scale => $c, $text => $d]));
        }
    }

    /**
     * Signature for Dana, a few text snippets and an unfinished draft.
     */
    private function createWritingAids(Organization $org, MailAccount $account, User $user, User $colleague): void
    {
        $signature = "Dana Demo\nVorsitz · SEV Musterstadt\nhttps://sev.example.org";
        $this->em->persist((new Signature($user, $account))->setBody($signature));
        $this->em->persist((new Signature($colleague, $account))->setBody("Kim Kollegin\nSchriftführung · SEV Musterstadt"));
        foreach ([
            'Eingangsbestätigung' => 'vielen Dank für Ihre Nachricht. Wir haben sie erhalten und melden uns, sobald wir uns im Vorstand abgestimmt haben.',
            'Einladung Sitzung' => "hiermit laden wir herzlich zur nächsten Sitzung der Stadtelternvertretung ein.\n\n- **Ort:** Rathaus, Raum 2\n- **Beginn:** 19:00 Uhr\n\nDie Tagesordnung folgt gesondert.",
            'Datenschutzhinweis' => '_Ihre Angaben verwenden wir ausschließlich für die Arbeit der Stadtelternvertretung und geben sie nicht an Dritte weiter._',
        ] as $title => $body) {
            $this->em->persist((new TextSnippet($org))->setTitle($title)->setBody($body));
        }

        $draft = new ComposeData();
        $draft->account = $account;
        $draft->to = 'Schulamt Musterstadt <info@schulamt.example.org>';
        $draft->subject = 'Termin Gesamtelternbeirat';
        $draft->body = "Sehr geehrte Damen und Herren,\n\n\n\n-- \n".$signature."\n";
        $this->em->persist((new Draft($user, $account))->apply($draft));
    }

    /**
     * Knowledge base: a few nested pages, one of them edited twice.
     */
    private function createWiki(Organization $org, User $user, User $colleague): void
    {
        $page = function (string $title, string $body, User $author, ?WikiPage $parent = null) use ($org): WikiPage {
            $page = (new WikiPage($org))->setTitle($title)->setBody($body)->setParent($parent);
            $this->em->persist($page);
            $this->em->persist($page->record($author));

            return $page;
        };
        $basics = $page('Grundlagen', "Was die Stadtelternvertretung macht und wie wir arbeiten.\n\n- Sitzungen alle zwei Wochen\n- Beschlüsse stehen in der **Beschlussliste**", $user);
        $page('Geschäftsordnung', "## Sitzungen\n\nDie Einladung geht mindestens **7 Tage** vorher raus.\n\n## Beschlüsse\n\nBeschlussfähig sind wir, wenn die Hälfte der Stimmberechtigten anwesend ist.", $user, $basics);
        $contacts = $page('Ansprechpartner', "- Schulamt: info@schulamt.example.org\n- Elternbeirat GS Nord: Eva Elternteil", $colleague, $basics);
        $contacts->setBody($contacts->getBody()."\n- Stadtverwaltung, Verkehrsplanung: Zimmer 214");
        $this->em->persist($contacts->record($user));
        $howto = $page('Anleitungen', 'Schritt-für-Schritt-Hilfen für wiederkehrende Aufgaben.', $colleague);
        $page('Protokoll schreiben', "1. Vorlage aus *Dateien → Protokolle* nehmen\n2. Beschlüsse mit Nummer festhalten\n3. Protokoll zur Freigabe in der Sitzung hochladen", $colleague, $howto);
        $page('Übergabe bei Amtswechsel', "Unter *Organisationen → Übergabe* gehen offene Nachrichten, Aufgaben und Projekte an die Nachfolge.\n\nDanach Amtszeit beenden.", $user, $howto);
    }

    /**
     * A minutes file with an older version, a share link and remembered entries.
     */
    private function createFiles(Folder $folder, User $user, User $colleague, Message $note, ForumTopic $topic): void
    {
        $draft = "# Protokoll Elternabend\n\n- Begrüßung\n- Schulwege\n";
        $final = $draft."- Termine für das neue Schuljahr\n\nProtokoll: Kim\n";
        $file = new StoredFile($folder, 'Protokoll-Elternabend.md', 'text/markdown', \strlen($draft), $this->files->store($draft), $colleague);
        $file->replaceWith('Protokoll-Elternabend.md', 'text/markdown', \strlen($final), $this->files->store($final), $user);
        $this->em->persist($file);
        $this->em->persist(new FileShare($file, $user, 7));
        $this->em->persist(ShelfItem::for($user, $file));
        $this->em->persist(ShelfItem::for($user, $note));
        $this->em->persist(ShelfItem::for($user, $topic));
    }

    /** @param list<Project> $projects */
    private function createMeetings(Organization $org, User $user, User $colleague, User $guest, array $projects): void
    {
        $past = (new Meeting($org, $user))->setTitle('Vollversammlung September')->setLocation('Rathaus, Sitzungssaal')
            ->setStartsAt(new \DateTimeImmutable('-3 weeks 19:00'))->setMinuteTaker($colleague)
            ->setMinutesNotes('Beginn 19:05 Uhr, Ende 20:50 Uhr.');
        $items = [];
        foreach ([
            ['Begrüßung und Genehmigung der Tagesordnung', 5, $user, null, 'Tagesordnung einstimmig genehmigt.'],
            ['Schulwegsicherheit: Zebrastreifen Grundschule Nord', 30, $colleague, $projects[0], 'Die Stadt prüft den Standort, eine Ortsbegehung ist geplant.'],
            ['Planung Elternabend Herbst', 20, $user, $projects[1], 'Termin und Aula stehen fest.'],
            ['Verschiedenes', 10, null, null, null],
        ] as [$title, $minutes, $responsible, $project, $notes]) {
            $past->addAgendaItem($items[] = $item = (new AgendaItem($past))->setTitle($title)->setDurationMinutes($minutes)
                ->setResponsible($responsible)->setMinutes($notes));
            if (null !== $project) {
                $item->setDescription('Projekt: '.$project->getName());
            }
        }
        $past->addAttendance(new Attendance($past, $user));
        $past->addAttendance(new Attendance($past, $colleague));
        $past->addAttendance(new Attendance($past, $guest, AttendanceStatus::Excused));
        $past->markInvited()->markHeld()->approveMinutes($user);
        $this->em->persist($past);
        $this->meetings->syncCalendar($past);

        $year = (int) $past->getStartsAt()->format('Y');
        $this->em->persist((new Resolution($org, $user))->setNumber($year.'/1')->setAgendaItem($items[1])->setProject($projects[0])
            ->setTitle('Antrag auf Zebrastreifen')->setDecidedOn($past->getStartsAt())->setAdopted(true)->setPublic(true)
            ->setText('Die SEV beantragt bei der Stadt einen Zebrastreifen vor der **Grundschule Nord**.')
            ->setVotesYes(2)->setVotesNo(0)->setVotesAbstain(0));
        $this->em->persist((new Resolution($org, $user))->setNumber($year.'/2')->setAgendaItem($items[2])->setProject($projects[1])
            ->setTitle('Budget Elternabend')->setDecidedOn($past->getStartsAt())->setAdopted(true)
            ->setText('Für Getränke und Material werden bis zu 150 Euro freigegeben.'));
        $this->em->persist((new CalendarItem($user))->setTitle('Ortsbegehung mit der Stadt vereinbaren')->setType(CalendarItemType::Task)
            ->setOrganization($org)->setProject($projects[0])->setAgendaItem($items[1])->setStartsAt(null)->addAssignee($colleague));

        $next = (new Meeting($org, $user))->setTitle('Vollversammlung Oktober')->setLocation('Rathaus, Sitzungssaal')
            ->setVideoUrl('https://meet.example.org/sev')->setStartsAt(new \DateTimeImmutable('+10 days 19:00'))->setMinuteTaker($user)
            ->setGuestEmails('info@schulamt.example.org');
        foreach ([['Begrüßung', 5, $user], ['Bericht Ortsbegehung', 15, $colleague], ['Rückblick Elternabend', 15, $user], ['Verschiedenes', 10, null]] as [$title, $minutes, $responsible]) {
            $next->addAgendaItem((new AgendaItem($next))->setTitle($title)->setDurationMinutes($minutes)->setResponsible($responsible));
        }
        $next->addAgendaItem((new AgendaItem($next))->setTitle('Schulobst-Programm')->setDescription('Können wir uns dafür einsetzen?')->propose($colleague));
        $this->em->persist($next);
        $this->meetings->syncCalendar($next);
    }

    /** @param list<Project> $projects */
    private function createPolls(Organization $org, User $user, User $colleague, ForumTopic $topic, array $projects): void
    {
        /**
         * @param list<string>                   $labels
         * @param array<string, array<int, int>> $votes  voter email => [option index => answer]
         */
        $poll = function (Poll $poll, array $labels, array $votes = [], array $slots = []) use ($user, $colleague): Poll {
            foreach ($labels as $i => $label) {
                $option = new PollOption($poll)->setLabel($label);
                if (isset($slots[$i])) {
                    $option->setPeriod($slots[$i], $slots[$i]->modify('+2 hours'));
                }
                $poll->addOption($option);
            }
            foreach ([$user, $colleague] as $voter) {
                if (!isset($votes[$voter->getEmail()])) {
                    continue;
                }
                $poll->addBallot($ballot = new PollBallot($poll, $voter));
                foreach ($votes[$voter->getEmail()] as $index => $value) {
                    $option = $poll->getOptions()[$index];
                    $option->addAnswer(new PollAnswer($option, $poll->isSecret() ? null : $ballot, $value));
                }
            }
            $this->em->persist($poll);

            return $poll;
        };

        $poll(new Poll($org, $colleague)->setTitle('Beitritt zum Bündnis „Sicherer Schulweg“')->setCircular(true)->setVotingOnly(true)
            ->setDescription('Die SEV tritt dem stadtweiten Bündnis bei und benennt eine Ansprechperson.')
            ->setProject($projects[0])->setDeadline(new \DateTimeImmutable('+5 days 18:00')),
            ['Ja', 'Nein', 'Enthaltung'], ['kim@koopio.test' => [0 => PollAnswer::YES]]);
        $poll(new Poll($org, $user)->setTitle('Welche Themen für den Elternabend?')->setKind(PollKind::Choice)->setMultiple(true)
            ->setTopic($topic)->setProject($projects[1])->setDeadline(new \DateTimeImmutable('+2 weeks 20:00')),
            ['Schulwegsicherheit', 'Ganztag', 'Digitalisierung', 'Schulobst'],
            ['demo@koopio.test' => [0 => PollAnswer::YES, 1 => PollAnswer::YES], 'kim@koopio.test' => [0 => PollAnswer::YES, 2 => PollAnswer::YES]]);
        $slots = [new \DateTimeImmutable('+8 days 10:00'), new \DateTimeImmutable('+9 days 15:00'), new \DateTimeImmutable('+12 days 10:00')];
        $poll(new Poll($org, $colleague)->setTitle('Ortsbegehung mit der Stadt')->setKind(PollKind::Schedule)->setProject($projects[0]),
            array_map(static fn (\DateTimeImmutable $d): string => $d->format('d.m.Y H:i'), $slots),
            ['kim@koopio.test' => [0 => PollAnswer::YES, 1 => PollAnswer::MAYBE, 2 => PollAnswer::YES]], $slots);
        $poll(new Poll($org, $user)->setTitle('Delegierte für den Landeselternrat')->setKind(PollKind::Choice)->setSecret(true)->close(),
            ['Dana Demo', 'Kim Kollegin'], ['demo@koopio.test' => [1 => PollAnswer::YES], 'kim@koopio.test' => [0 => PollAnswer::YES]]);
    }
}
