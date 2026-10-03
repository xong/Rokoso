<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CalendarItem;
use App\Entity\Contact;
use App\Entity\Folder;
use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\MailAccount;
use App\Entity\MailRule;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\MailEncryption;
use App\Enum\MailRuleField;
use App\Enum\MessageType;
use App\Enum\OrganizationRole;
use App\Enum\Recurrence;
use App\Mail\MailSynchronizer;
use App\Repository\UserRepository;
use App\Service\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
}
