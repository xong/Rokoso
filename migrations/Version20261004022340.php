<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004022340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendar: single occurrence changes, reminders, guests, iCal subscription token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE calendar_exception (id INT AUTO_INCREMENT NOT NULL, cancelled TINYINT NOT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, date DATE NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_3CA50527126F525EAA9E377A (item_id, date), INDEX IDX_3CA50527126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_exception ADD CONSTRAINT FK_3CA50527126F525E FOREIGN KEY (item_id) REFERENCES calendar_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E93363A255 ON app_user (calendar_token)');
        $this->addSql('ALTER TABLE calendar_item ADD reminder_minutes INT DEFAULT 1440, ADD guest_emails LONGTEXT DEFAULT NULL, ADD invited_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_exception DROP FOREIGN KEY FK_3CA50527126F525E');
        $this->addSql('DROP TABLE calendar_exception');
        $this->addSql('DROP INDEX UNIQ_88BDF3E93363A255 ON app_user');
        $this->addSql('ALTER TABLE app_user DROP calendar_token');
        $this->addSql('ALTER TABLE calendar_item DROP reminder_minutes, DROP guest_emails, DROP invited_at');
    }
}
