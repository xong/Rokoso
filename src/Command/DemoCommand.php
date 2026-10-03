<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AgendaItem;
use App\Entity\Attendance;
use App\Entity\CalendarItem;
use App\Entity\Contact;
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
use App\Entity\Project;
use App\Entity\Resolution;
use App\Entity\User;
use App\Entity\Watch;
use App\Enum\AttendanceStatus;
use App\Enum\CalendarItemType;
use App\Enum\MailEncryption;
use App\Enum\MailRuleField;
use App\Enum\MessageType;
use App\Enum\NotificationType;
use App\Enum\OrganizationRole;
use App\Enum\Recurrence;
use App\Enum\TaskStatus;
use App\Mail\MailSynchronizer;
use App\Meeting\MeetingService;
use App\Repository\UserRepository;
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
 * Local demo data: user demo@coop.test (password "demo-passwort"), organization, projects and a mail
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
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Beispiel-E-Mails an das GreenMail-Postfach schicken')] bool $mails = false): int
    {
        $user = $this->users->findOneByEmail('demo@coop.test');
        if (null === $user) {
            $user = (new User())->setEmail('demo@coop.test')->setName('Dana Demo')->setVerified(true);
            $user->setPassword($this->hasher->hashPassword($user, 'demo-passwort'));
            $this->em->persist($user);

            $colleague = (new User())->setEmail('kim@coop.test')->setName('Kim Kollegin')->setVerified(true);
            $colleague->setPassword($this->hasher->hashPassword($colleague, 'demo-passwort'));
            $this->em->persist($colleague);

            $org = (new Organization())->setName('SEV Musterstadt')->setColor('#2563eb')
                ->setDescription('Stadtelternvertretung der Musterstädter Schulen');
            $org->addMember($user, OrganizationRole::Admin);
            $org->addMember($colleague, OrganizationRole::Member);
            $guest = (new User())->setEmail('gast@coop.test')->setName('Gerd Gastmitglied')->setVerified(true);
            $guest->setPassword($this->hasher->hashPassword($guest, 'demo-passwort'));
            $this->em->persist($guest);
            $org->addMember($guest, OrganizationRole::Member)->setVotingRight(false);
            $this->em->persist($org);

            $projects = [];
            foreach (['Schulwegsicherheit' => '#16a34a', 'Elternabend Herbst' => '#ea580c'] as $name => $color) {
                $projects[] = $project = (new Project($user))->setName($name)->setColor($color)->setOrganization($org);
                $this->em->persist($project);
            }

            foreach ([['Eva', 'Elternteil', 'Elternbeirat GS Nord', 'eva@example.org'], [null, null, 'Schulamt Musterstadt', 'info@schulamt.example.org']] as [$first, $last, $company, $email]) {
                $this->em->persist((new Contact($user))->setFirstName($first)->setLastName($last)->setCompany($company)->setEmail($email)->setOrganization($org));
            }

            $monday = new \DateTimeImmutable('monday this week');
            $this->em->persist((new CalendarItem($user))->setTitle('Vorstandstreffen')->setOrganization($org)->setLocation('Rathaus, Raum 2')
                ->setStartsAt($monday->setTime(19, 0))->setEndsAt($monday->setTime(21, 0))
                ->setRecurrence(Recurrence::Weekly)->setRecurrenceInterval(2)->addParticipant($user)->addParticipant($colleague));
            $this->em->persist((new CalendarItem($user))->setTitle('Elternabend')->setOrganization($org)->setProject($projects[1])
                ->setStartsAt($monday->modify('+9 days')->setTime(19, 30))->setEndsAt($monday->modify('+9 days')->setTime(21, 0))->setLocation('Aula'));
            $this->em->persist((new CalendarItem($user))->setTitle('Protokoll verschicken')->setType(CalendarItemType::Task)->setOrganization($org)
                ->setStartsAt($monday->modify('+3 days')->setTime(12, 0))->addAssignee($colleague));

            $note = (new Message(MessageType::Internal))->setAuthor($colleague)->setFrom($colleague->getEmail(), $colleague->getName())
                ->setOrganization($org)->setSubject('Willkommen in Coop')
                ->setBody("Hallo zusammen,\n\nhier können wir Mails gemeinsam bearbeiten, kommentieren und Verantwortliche festlegen.\n\nKim");
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
            $this->em->persist(new Watch($user, $general));
            $this->em->persist(new Watch($user, $projects[0]));
            $this->em->persist(new Watch($colleague, $topic));
            $this->em->flush();
            $topicUrl = $this->urls->generate('forum_topic_show', ['id' => $topic->getId()]);
            $this->em->persist(new Notification($user, NotificationType::Post, $topic->getTitle(), $topicUrl, $colleague));
            $this->em->persist(new Notification($colleague, NotificationType::Mentioned, $topic->getTitle(), $topicUrl, $user));

            $account = (new MailAccount($org))
                ->setName('Postfach SEV')
                ->setEmailAddress('sev@coop.test')
                ->setSenderName('SEV Musterstadt')
                ->setImapHost('127.0.0.1')->setImapPort(3143)->setImapEncryption(MailEncryption::None)
                ->setImapUsername('sev@coop.test')->setImapPassword($this->secretBox->encrypt('demo'))
                ->setSmtpHost('127.0.0.1')->setSmtpPort(3025)->setSmtpEncryption(MailEncryption::None);
            $this->em->persist($account);
            $this->em->persist((new MailRule($org))->setName('Rundbrief Landeselternrat')->setField(MailRuleField::From)
                ->setNeedle('newsletter@')->setMarkDone(true));
            $this->em->flush();
            $io->success('Demo angelegt: demo@coop.test / demo-passwort (und kim@coop.test)');
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
                $email = (new Email())->from($from)->to('sev@coop.test')->subject($subject)->text($text)
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

        foreach ($this->em->getRepository(MailAccount::class)->findBy(['emailAddress' => 'sev@coop.test']) as $account) {
            $io->writeln(\sprintf('Abruf: %d neue E-Mail(s)%s', $this->synchronizer->sync($account), $account->getLastSyncError() ? ' – Fehler: '.$account->getLastSyncError() : ''));
        }

        return 0;
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
            ->setTitle('Antrag auf Zebrastreifen')->setDecidedOn($past->getStartsAt())->setAdopted(true)
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
}
