<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\User;
use App\Repository\MailAccountRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fetches the user's mail accounts when the app is opened, at most every MAIL_SYNC_INTERVAL minutes per account
 * (0 = only by cron). Complements the cron job, so new mails show up quickly even with a slow cron.
 */
final readonly class SyncOnOpen
{
    public function __construct(
        private MailAccountRepository $accounts,
        private MailSynchronizer $synchronizer,
        #[Autowire(env: 'int:MAIL_SYNC_INTERVAL')]
        private int $intervalMinutes,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->intervalMinutes > 0;
    }

    /**
     * @return int number of imported messages
     */
    public function run(User $user): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        $due = new \DateTimeImmutable(\sprintf('-%d minutes', $this->intervalMinutes));
        $imported = 0;
        foreach ($this->accounts->findForUser($user) as $account) {
            $last = $account->getLastSyncAt();
            if ($account->isEnabled() && (null === $last || $last < $due) && $this->accounts->claimSync($account, $due)) {
                $imported += $this->synchronizer->sync($account, 50);
            }
        }

        return $imported;
    }
}
