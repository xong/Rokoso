<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004011509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drafts with outbox, signatures, text snippets, sent folder per mail account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE draft (id INT AUTO_INCREMENT NOT NULL, to_addresses LONGTEXT NOT NULL, cc_addresses LONGTEXT NOT NULL, bcc_addresses LONGTEXT NOT NULL, subject VARCHAR(500) NOT NULL, body LONGTEXT NOT NULL, forward TINYINT NOT NULL, keep_attachments TINYINT NOT NULL, files JSON NOT NULL, send_at DATETIME DEFAULT NULL, send_error VARCHAR(1000) DEFAULT NULL, updated_at DATETIME NOT NULL, account_id INT NOT NULL, project_id INT DEFAULT NULL, original_id INT DEFAULT NULL, owner_id INT NOT NULL, INDEX IDX_467C9694C6FDA417 (send_at), INDEX IDX_467C96949B6B5FBA (account_id), INDEX IDX_467C9694166D1F9C (project_id), INDEX IDX_467C9694108B7592 (original_id), INDEX IDX_467C96947E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE signature (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, user_id INT NOT NULL, account_id INT NOT NULL, UNIQUE INDEX UNIQ_AE880141A76ED3959B6B5FBA (user_id, account_id), INDEX IDX_AE880141A76ED395 (user_id), INDEX IDX_AE8801419B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE text_snippet (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, body LONGTEXT NOT NULL, organization_id INT NOT NULL, INDEX IDX_B2BDA3EA32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C96949B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C9694166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C9694108B7592 FOREIGN KEY (original_id) REFERENCES message (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C96947E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE signature ADD CONSTRAINT FK_AE880141A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE signature ADD CONSTRAINT FK_AE8801419B6B5FBA FOREIGN KEY (account_id) REFERENCES mail_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE text_snippet ADD CONSTRAINT FK_B2BDA3EA32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_account ADD sent_folder VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE draft DROP FOREIGN KEY FK_467C96949B6B5FBA');
        $this->addSql('ALTER TABLE draft DROP FOREIGN KEY FK_467C9694166D1F9C');
        $this->addSql('ALTER TABLE draft DROP FOREIGN KEY FK_467C9694108B7592');
        $this->addSql('ALTER TABLE draft DROP FOREIGN KEY FK_467C96947E3C61F9');
        $this->addSql('ALTER TABLE signature DROP FOREIGN KEY FK_AE880141A76ED395');
        $this->addSql('ALTER TABLE signature DROP FOREIGN KEY FK_AE8801419B6B5FBA');
        $this->addSql('ALTER TABLE text_snippet DROP FOREIGN KEY FK_B2BDA3EA32C8A3DE');
        $this->addSql('DROP TABLE draft');
        $this->addSql('DROP TABLE signature');
        $this->addSql('DROP TABLE text_snippet');
        $this->addSql('ALTER TABLE mail_account DROP sent_folder');
    }
}
