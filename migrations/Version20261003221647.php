<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003221647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mail_rule (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, field VARCHAR(20) NOT NULL, needle VARCHAR(255) NOT NULL, mark_done TINYINT NOT NULL, enabled TINYINT NOT NULL, mail_account_id INT DEFAULT NULL, project_id INT DEFAULT NULL, organization_id INT NOT NULL, INDEX IDX_EBA33FA54FA8B546 (mail_account_id), INDEX IDX_EBA33FA5166D1F9C (project_id), INDEX IDX_EBA33FA532C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mail_rule_assignee (mail_rule_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_AA431DACAEF3823E (mail_rule_id), INDEX IDX_AA431DACA76ED395 (user_id), PRIMARY KEY (mail_rule_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_event (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(20) NOT NULL, detail VARCHAR(255) DEFAULT NULL, message_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_C408F54C537A1329 (message_id), INDEX IDX_C408F54CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mail_rule ADD CONSTRAINT FK_EBA33FA54FA8B546 FOREIGN KEY (mail_account_id) REFERENCES mail_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_rule ADD CONSTRAINT FK_EBA33FA5166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mail_rule ADD CONSTRAINT FK_EBA33FA532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_rule_assignee ADD CONSTRAINT FK_AA431DACAEF3823E FOREIGN KEY (mail_rule_id) REFERENCES mail_rule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_rule_assignee ADD CONSTRAINT FK_AA431DACA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_event ADD CONSTRAINT FK_C408F54C537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_event ADD CONSTRAINT FK_C408F54CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD done_at DATETIME DEFAULT NULL, ADD snoozed_until DATETIME DEFAULT NULL, ADD thread_key VARCHAR(255) DEFAULT NULL, ADD done_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F35AE3EF9 FOREIGN KEY (done_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B6BD307F85CDFBBA ON message (thread_key)');
        $this->addSql('CREATE INDEX IDX_B6BD307F35AE3EF9 ON message (done_by_id)');
        // Status model: messages already filed into a project were "out of the inbox" before → done.
        $this->addSql('UPDATE message SET done_at = created_at WHERE project_id IS NOT NULL');
        $this->addSql('UPDATE message SET thread_key = message_id_header WHERE message_id_header IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mail_rule DROP FOREIGN KEY FK_EBA33FA54FA8B546');
        $this->addSql('ALTER TABLE mail_rule DROP FOREIGN KEY FK_EBA33FA5166D1F9C');
        $this->addSql('ALTER TABLE mail_rule DROP FOREIGN KEY FK_EBA33FA532C8A3DE');
        $this->addSql('ALTER TABLE mail_rule_assignee DROP FOREIGN KEY FK_AA431DACAEF3823E');
        $this->addSql('ALTER TABLE mail_rule_assignee DROP FOREIGN KEY FK_AA431DACA76ED395');
        $this->addSql('ALTER TABLE message_event DROP FOREIGN KEY FK_C408F54C537A1329');
        $this->addSql('ALTER TABLE message_event DROP FOREIGN KEY FK_C408F54CA76ED395');
        $this->addSql('DROP TABLE mail_rule');
        $this->addSql('DROP TABLE mail_rule_assignee');
        $this->addSql('DROP TABLE message_event');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F35AE3EF9');
        $this->addSql('DROP INDEX IDX_B6BD307F85CDFBBA ON message');
        $this->addSql('DROP INDEX IDX_B6BD307F35AE3EF9 ON message');
        $this->addSql('ALTER TABLE message DROP done_at, DROP snoozed_until, DROP thread_key, DROP done_by_id');
    }
}
