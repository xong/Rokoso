<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Entity\MailAccount;
use App\Mail\SentFolderWriter;

/**
 * Collects appended messages per folder.
 */
final class FakeSentFolderWriter implements SentFolderWriter
{
    /** @var list<array{folder: string, raw: string}> */
    public array $appended = [];

    public function append(MailAccount $account, string $raw): void
    {
        $this->appended[] = ['folder' => (string) $account->getSentFolder(), 'raw' => $raw];
    }
}
