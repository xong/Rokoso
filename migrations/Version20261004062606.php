<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004062606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Two-factor login, session stamp, platform admin, blocked/deleted accounts, security log, deletion periods';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE security_event (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(40) NOT NULL, detail VARCHAR(255) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_id INT DEFAULT NULL, actor_id INT DEFAULT NULL, INDEX IDX_D712E90D8B8E8428 (created_at), INDEX IDX_D712E90DA76ED395 (user_id), INDEX IDX_D712E90D10DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE security_event ADD CONSTRAINT FK_D712E90DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE security_event ADD CONSTRAINT FK_D712E90D10DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_user ADD totp_secret VARCHAR(64) DEFAULT NULL, ADD backup_codes JSON DEFAULT \'[]\' NOT NULL, ADD session_stamp VARCHAR(32) DEFAULT \'\' NOT NULL, ADD platform_admin TINYINT DEFAULT 0 NOT NULL, ADD blocked_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE app_user SET session_stamp = MD5(CONCAT(id, RAND()))');
        $this->addSql('ALTER TABLE organization ADD trash_days INT DEFAULT 30 NOT NULL, ADD message_retention_years INT DEFAULT NULL, ADD submission_retention_years INT DEFAULT 2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_event DROP FOREIGN KEY FK_D712E90DA76ED395');
        $this->addSql('ALTER TABLE security_event DROP FOREIGN KEY FK_D712E90D10DAF24A');
        $this->addSql('DROP TABLE security_event');
        $this->addSql('ALTER TABLE app_user DROP totp_secret, DROP backup_codes, DROP session_stamp, DROP platform_admin, DROP blocked_at, DROP deleted_at');
        $this->addSql('ALTER TABLE organization DROP trash_days, DROP message_retention_years, DROP submission_retention_years');
    }
}
