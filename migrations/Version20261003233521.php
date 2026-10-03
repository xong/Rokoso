<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Polls: votes, circular resolutions, date finding.
 */
final class Version20261003233521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Polls with options, ballots and answers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE poll (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, kind VARCHAR(20) NOT NULL, multiple TINYINT NOT NULL, secret TINYINT NOT NULL, voting_only TINYINT NOT NULL, circular TINYINT NOT NULL, deadline DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, chosen_starts_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, project_id INT DEFAULT NULL, meeting_id INT DEFAULT NULL, agenda_item_id INT DEFAULT NULL, topic_id INT DEFAULT NULL, resolution_id INT DEFAULT NULL, calendar_item_id INT DEFAULT NULL, organization_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_84BCFA45166D1F9C (project_id), INDEX IDX_84BCFA4567433D9C (meeting_id), INDEX IDX_84BCFA4591AB301F (agenda_item_id), INDEX IDX_84BCFA451F55203D (topic_id), INDEX IDX_84BCFA4512A1C43A (resolution_id), INDEX IDX_84BCFA4570182999 (calendar_item_id), INDEX IDX_84BCFA4532C8A3DE (organization_id), INDEX IDX_84BCFA45B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE poll_answer (id INT AUTO_INCREMENT NOT NULL, value SMALLINT NOT NULL, option_id INT NOT NULL, ballot_id INT DEFAULT NULL, INDEX IDX_36D8097EA7C41D6F (option_id), INDEX IDX_36D8097EDDC23F6C (ballot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE poll_ballot (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, poll_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3999AAE63C947C0FA76ED395 (poll_id, user_id), INDEX IDX_3999AAE63C947C0F (poll_id), INDEX IDX_3999AAE6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE poll_option (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, label VARCHAR(200) NOT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, poll_id INT NOT NULL, INDEX IDX_B68343EB3C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA45166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4567433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4591AB301F FOREIGN KEY (agenda_item_id) REFERENCES agenda_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA451F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4512A1C43A FOREIGN KEY (resolution_id) REFERENCES resolution (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4570182999 FOREIGN KEY (calendar_item_id) REFERENCES calendar_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA45B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_answer ADD CONSTRAINT FK_36D8097EA7C41D6F FOREIGN KEY (option_id) REFERENCES poll_option (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_answer ADD CONSTRAINT FK_36D8097EDDC23F6C FOREIGN KEY (ballot_id) REFERENCES poll_ballot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_ballot ADD CONSTRAINT FK_3999AAE63C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_ballot ADD CONSTRAINT FK_3999AAE6A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_B68343EB3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA45166D1F9C');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4567433D9C');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4591AB301F');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA451F55203D');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4512A1C43A');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4570182999');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4532C8A3DE');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA45B03A8386');
        $this->addSql('ALTER TABLE poll_answer DROP FOREIGN KEY FK_36D8097EA7C41D6F');
        $this->addSql('ALTER TABLE poll_answer DROP FOREIGN KEY FK_36D8097EDDC23F6C');
        $this->addSql('ALTER TABLE poll_ballot DROP FOREIGN KEY FK_3999AAE63C947C0F');
        $this->addSql('ALTER TABLE poll_ballot DROP FOREIGN KEY FK_3999AAE6A76ED395');
        $this->addSql('ALTER TABLE poll_option DROP FOREIGN KEY FK_B68343EB3C947C0F');
        $this->addSql('DROP TABLE poll');
        $this->addSql('DROP TABLE poll_answer');
        $this->addSql('DROP TABLE poll_ballot');
        $this->addSql('DROP TABLE poll_option');
    }
}
