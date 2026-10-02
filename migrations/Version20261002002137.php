<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002002137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, storage_path VARCHAR(255) NOT NULL, content_id VARCHAR(255) DEFAULT NULL, message_id INT NOT NULL, INDEX IDX_795FD9BB537A1329 (message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, message_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_9474526C537A1329 (message_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, folder VARCHAR(20) NOT NULL, imap_uid INT DEFAULT NULL, message_id_header VARCHAR(255) DEFAULT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, references_header LONGTEXT DEFAULT NULL, from_name VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, reply_to_address VARCHAR(255) DEFAULT NULL, to_recipients JSON NOT NULL, cc_recipients JSON NOT NULL, subject VARCHAR(500) NOT NULL, snippet VARCHAR(255) NOT NULL, text_body LONGTEXT DEFAULT NULL, html_body LONGTEXT DEFAULT NULL, date DATETIME NOT NULL, created_at DATETIME NOT NULL, trashed_at DATETIME DEFAULT NULL, type VARCHAR(20) NOT NULL, organization_id INT DEFAULT NULL, mail_account_id INT DEFAULT NULL, author_id INT DEFAULT NULL, project_id INT DEFAULT NULL, INDEX IDX_B6BD307FAA9E377A (date), INDEX IDX_B6BD307F22FC1B1A (message_id_header), INDEX IDX_B6BD307F32C8A3DE (organization_id), INDEX IDX_B6BD307F4FA8B546 (mail_account_id), INDEX IDX_B6BD307FF675F31B (author_id), INDEX IDX_B6BD307F166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_assignee (message_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_75BB381D537A1329 (message_id), INDEX IDX_75BB381DA76ED395 (user_id), PRIMARY KEY (message_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_recipient (message_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2BDFD7F537A1329 (message_id), INDEX IDX_2BDFD7FA76ED395 (user_id), PRIMARY KEY (message_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_read (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME NOT NULL, message_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_31C2DABE537A1329A76ED395 (message_id, user_id), INDEX IDX_31C2DABE537A1329 (message_id), INDEX IDX_31C2DABEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F4FA8B546 FOREIGN KEY (mail_account_id) REFERENCES mail_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message_assignee ADD CONSTRAINT FK_75BB381D537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_assignee ADD CONSTRAINT FK_75BB381DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_recipient ADD CONSTRAINT FK_2BDFD7F537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_recipient ADD CONSTRAINT FK_2BDFD7FA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_read ADD CONSTRAINT FK_31C2DABE537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_read ADD CONSTRAINT FK_31C2DABEA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB537A1329');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C537A1329');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F32C8A3DE');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F4FA8B546');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF675F31B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F166D1F9C');
        $this->addSql('ALTER TABLE message_assignee DROP FOREIGN KEY FK_75BB381D537A1329');
        $this->addSql('ALTER TABLE message_assignee DROP FOREIGN KEY FK_75BB381DA76ED395');
        $this->addSql('ALTER TABLE message_recipient DROP FOREIGN KEY FK_2BDFD7F537A1329');
        $this->addSql('ALTER TABLE message_recipient DROP FOREIGN KEY FK_2BDFD7FA76ED395');
        $this->addSql('ALTER TABLE message_read DROP FOREIGN KEY FK_31C2DABE537A1329');
        $this->addSql('ALTER TABLE message_read DROP FOREIGN KEY FK_31C2DABEA76ED395');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE message_assignee');
        $this->addSql('DROP TABLE message_recipient');
        $this->addSql('DROP TABLE message_read');
    }
}
