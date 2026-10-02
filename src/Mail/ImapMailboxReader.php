<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\MailAccount;
use App\Enum\MailEncryption;
use App\Service\SecretBox;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;

/**
 * IMAP access via webklex/php-imap. The folder is opened with EXAMINE (read-only),
 * so fetching never sets the \Seen flag on the server.
 */
final readonly class ImapMailboxReader implements MailboxReader
{
    private const int FETCH_CHUNK = 20;

    public function __construct(private SecretBox $secretBox)
    {
    }

    public function fetchNew(MailAccount $account, int $limit = 200): FetchResult
    {
        $client = $this->connect($account);
        try {
            $protocol = $this->protocol($client);
            $status = $protocol->examineFolder($account->getInboxFolder())->validatedData();
            $uidValidity = (int) ($status['uidvalidity'] ?? 0);

            $lastUid = $account->getUidValidity() === $uidValidity ? $account->getLastUid() : null;
            if (null === $lastUid) {
                $since = new \DateTimeImmutable(\sprintf('-%d days', $account->getImportDays()));
                $params = ['SINCE', $since->format('d-M-Y')];
            } else {
                $params = ['UID', ($lastUid + 1).':*'];
            }

            /** @var list<int|string> $found */
            $found = $protocol->search($params)->data();
            $uids = array_values(array_filter(array_map(intval(...), $found), static fn (int $uid): bool => $uid > ($lastUid ?? 0)));
            sort($uids);
            $complete = \count($uids) <= $limit;
            $uids = \array_slice($uids, 0, $limit);

            $messages = [];
            foreach (array_chunk($uids, self::FETCH_CHUNK) as $chunk) {
                /** @var array<int, mixed> $raw */
                $raw = $protocol->fetch(['RFC822'], $chunk)->validatedData();
                foreach ($chunk as $uid) {
                    if (isset($raw[$uid]) && \is_string($raw[$uid])) {
                        $messages[$uid] = $raw[$uid];
                    }
                }
            }

            return new FetchResult($uidValidity, $messages, $complete);
        } finally {
            $client->disconnect();
        }
    }

    public function test(MailAccount $account): void
    {
        $client = $this->connect($account);
        try {
            $this->protocol($client)->examineFolder($account->getInboxFolder())->validatedData();
        } finally {
            $client->disconnect();
        }
    }

    private function connect(MailAccount $account): Client
    {
        $password = null === $account->getImapPassword() ? '' : $this->secretBox->decrypt($account->getImapPassword());
        $client = new ClientManager()->make([
            'host' => $account->getImapHost(),
            'port' => $account->getImapPort(),
            'encryption' => match ($account->getImapEncryption()) {
                MailEncryption::Ssl => 'ssl',
                MailEncryption::StartTls => 'starttls',
                MailEncryption::None => false,
            },
            'validate_cert' => true,
            'username' => $account->getImapUsername(),
            'password' => $password,
            'protocol' => 'imap',
            'timeout' => 30,
        ]);
        $client->connect();

        return $client;
    }

    private function protocol(Client $client): ImapProtocol
    {
        $protocol = $client->getConnection();
        \assert($protocol instanceof ImapProtocol);

        return $protocol;
    }
}
