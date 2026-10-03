<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003230838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Meetings with agenda, attendance, minutes and resolutions; quorum settings and voting right';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agenda_item (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, duration_minutes INT DEFAULT NULL, proposed TINYINT NOT NULL, minutes LONGTEXT DEFAULT NULL, responsible_id INT DEFAULT NULL, proposed_by_id INT DEFAULT NULL, meeting_id INT NOT NULL, INDEX IDX_223E876E602AD315 (responsible_id), INDEX IDX_223E876EDAB5A938 (proposed_by_id), INDEX IDX_223E876E67433D9C (meeting_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE agenda_item_file (agenda_item_id INT NOT NULL, stored_file_id INT NOT NULL, INDEX IDX_E718F1F91AB301F (agenda_item_id), INDEX IDX_E718F1F7590B9E4 (stored_file_id), PRIMARY KEY (agenda_item_id, stored_file_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE attendance (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, meeting_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_6DE30D9167433D9CA76ED395 (meeting_id, user_id), INDEX IDX_6DE30D9167433D9C (meeting_id), INDEX IDX_6DE30D91A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE meeting (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, video_url VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, guest_emails LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, minutes_notes LONGTEXT DEFAULT NULL, invited_at DATETIME DEFAULT NULL, minutes_approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, project_id INT DEFAULT NULL, minute_taker_id INT DEFAULT NULL, minutes_approved_by_id INT DEFAULT NULL, organization_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_F515E13955A0507C (starts_at), INDEX IDX_F515E139166D1F9C (project_id), INDEX IDX_F515E139B946AF23 (minute_taker_id), INDEX IDX_F515E13924274A36 (minutes_approved_by_id), INDEX IDX_F515E13932C8A3DE (organization_id), INDEX IDX_F515E139B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resolution (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(20) NOT NULL, title VARCHAR(200) NOT NULL, text LONGTEXT NOT NULL, decided_on DATE NOT NULL, adopted TINYINT NOT NULL, votes_yes INT DEFAULT NULL, votes_no INT DEFAULT NULL, votes_abstain INT DEFAULT NULL, created_at DATETIME NOT NULL, meeting_id INT DEFAULT NULL, agenda_item_id INT DEFAULT NULL, project_id INT DEFAULT NULL, organization_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_FDD30F8A34442166 (decided_on), INDEX IDX_FDD30F8A67433D9C (meeting_id), INDEX IDX_FDD30F8A91AB301F (agenda_item_id), INDEX IDX_FDD30F8A166D1F9C (project_id), INDEX IDX_FDD30F8A32C8A3DE (organization_id), INDEX IDX_FDD30F8AB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876E602AD315 FOREIGN KEY (responsible_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876EDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876E67433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agenda_item_file ADD CONSTRAINT FK_E718F1F91AB301F FOREIGN KEY (agenda_item_id) REFERENCES agenda_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agenda_item_file ADD CONSTRAINT FK_E718F1F7590B9E4 FOREIGN KEY (stored_file_id) REFERENCES stored_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D9167433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139B946AF23 FOREIGN KEY (minute_taker_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E13924274A36 FOREIGN KEY (minutes_approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E13932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resolution ADD CONSTRAINT FK_FDD30F8A67433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE resolution ADD CONSTRAINT FK_FDD30F8A91AB301F FOREIGN KEY (agenda_item_id) REFERENCES agenda_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE resolution ADD CONSTRAINT FK_FDD30F8A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE resolution ADD CONSTRAINT FK_FDD30F8A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resolution ADD CONSTRAINT FK_FDD30F8AB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item ADD agenda_item_id INT DEFAULT NULL, ADD meeting_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A91AB301F FOREIGN KEY (agenda_item_id) REFERENCES agenda_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A67433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_4D8DA64A91AB301F ON calendar_item (agenda_item_id)');
        $this->addSql('CREATE INDEX IDX_4D8DA64A67433D9C ON calendar_item (meeting_id)');
        $this->addSql('ALTER TABLE membership ADD voting_right TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE organization ADD quorum_percent INT DEFAULT 50 NOT NULL, ADD invitation_days INT DEFAULT 7 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876E602AD315');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876EDAB5A938');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876E67433D9C');
        $this->addSql('ALTER TABLE agenda_item_file DROP FOREIGN KEY FK_E718F1F91AB301F');
        $this->addSql('ALTER TABLE agenda_item_file DROP FOREIGN KEY FK_E718F1F7590B9E4');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D9167433D9C');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91A76ED395');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139166D1F9C');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139B946AF23');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E13924274A36');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E13932C8A3DE');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139B03A8386');
        $this->addSql('ALTER TABLE resolution DROP FOREIGN KEY FK_FDD30F8A67433D9C');
        $this->addSql('ALTER TABLE resolution DROP FOREIGN KEY FK_FDD30F8A91AB301F');
        $this->addSql('ALTER TABLE resolution DROP FOREIGN KEY FK_FDD30F8A166D1F9C');
        $this->addSql('ALTER TABLE resolution DROP FOREIGN KEY FK_FDD30F8A32C8A3DE');
        $this->addSql('ALTER TABLE resolution DROP FOREIGN KEY FK_FDD30F8AB03A8386');
        $this->addSql('DROP TABLE agenda_item');
        $this->addSql('DROP TABLE agenda_item_file');
        $this->addSql('DROP TABLE attendance');
        $this->addSql('DROP TABLE meeting');
        $this->addSql('DROP TABLE resolution');
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A91AB301F');
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A67433D9C');
        $this->addSql('DROP INDEX IDX_4D8DA64A91AB301F ON calendar_item');
        $this->addSql('DROP INDEX IDX_4D8DA64A67433D9C ON calendar_item');
        $this->addSql('ALTER TABLE calendar_item DROP agenda_item_id, DROP meeting_id');
        $this->addSql('ALTER TABLE membership DROP voting_right');
        $this->addSql('ALTER TABLE organization DROP quorum_percent, DROP invitation_days');
    }
}
