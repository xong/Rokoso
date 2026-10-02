<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MailAccount;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MailEncryption;
use App\Enum\OrganizationRole;
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

            foreach (['Schulwegsicherheit' => '#16a34a', 'Elternabend Herbst' => '#ea580c'] as $name => $color) {
                $this->em->persist((new Project($user))->setName($name)->setColor($color)->setOrganization($org));
            }

            $account = (new MailAccount($org))
                ->setName('Postfach SEV')
                ->setEmailAddress('sev@coop.test')
                ->setSenderName('SEV Musterstadt')
                ->setImapHost('127.0.0.1')->setImapPort(3143)->setImapEncryption(MailEncryption::None)
                ->setImapUsername('sev@coop.test')->setImapPassword($this->secretBox->encrypt('demo'))
                ->setSmtpHost('127.0.0.1')->setSmtpPort(3025)->setSmtpEncryption(MailEncryption::None);
            $this->em->persist($account);
            $this->em->flush();
            $io->success('Demo angelegt: demo@coop.test / demo-passwort (und kim@coop.test)');
        }

        if ($mails) {
            $transport = Transport::fromDsn('smtp://127.0.0.1:3025?auto_tls=false');
            $samples = [
                ['Eva Elternteil <eva@example.org>', 'Zebrastreifen an der Grundschule Nord', "Hallo zusammen,\n\nwie ist der Stand beim Zebrastreifen? Die Kinder müssen dort jeden Morgen über die Straße.\n\nViele Grüße\nEva"],
                ['Schulamt Musterstadt <info@schulamt.example.org>', 'Einladung: Gesamtelternbeirat am 15.10.', "Sehr geehrte Damen und Herren,\n\nanbei die Einladung und Tagesordnung.\n\nMit freundlichen Grüßen\nSchulamt"],
                ['Bert Beispiel <bert@example.org>', 'Re: Elternabend Herbst', "Ich kann den Raum in der Aula organisieren.\n\nBert"],
            ];
            foreach ($samples as [$from, $subject, $text]) {
                $email = (new Email())->from($from)->to('sev@coop.test')->subject($subject)->text($text)
                    ->html('<p>'.nl2br(htmlspecialchars($text)).'</p>');
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
