<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002001651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mail_account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email_address VARCHAR(180) NOT NULL, sender_name VARCHAR(120) DEFAULT NULL, imap_host VARCHAR(180) NOT NULL, imap_port INT NOT NULL, imap_encryption VARCHAR(10) NOT NULL, imap_username VARCHAR(180) NOT NULL, imap_password LONGTEXT DEFAULT NULL, smtp_host VARCHAR(180) NOT NULL, smtp_port INT NOT NULL, smtp_encryption VARCHAR(10) NOT NULL, smtp_username VARCHAR(180) DEFAULT NULL, smtp_password LONGTEXT DEFAULT NULL, inbox_folder VARCHAR(120) NOT NULL, import_days INT NOT NULL, enabled TINYINT NOT NULL, last_uid INT DEFAULT NULL, uid_validity INT DEFAULT NULL, last_sync_at DATETIME DEFAULT NULL, last_sync_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, INDEX IDX_A78BD7CB32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mail_account ADD CONSTRAINT FK_A78BD7CB32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mail_account DROP FOREIGN KEY FK_A78BD7CB32C8A3DE');
        $this->addSql('DROP TABLE mail_account');
    }
}
