<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122101903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Daily summary of new mails per user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD mail_digest TINYINT DEFAULT 0 NOT NULL, ADD last_mail_digest_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP mail_digest, DROP last_mail_digest_at');
    }
}
