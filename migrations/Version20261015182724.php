<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015182724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Several projects per message (message_project), existing assignments are kept';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_project (message_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_BF3BEAE2537A1329 (message_id), INDEX IDX_BF3BEAE2166D1F9C (project_id), PRIMARY KEY (message_id, project_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message_project ADD CONSTRAINT FK_BF3BEAE2537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_project ADD CONSTRAINT FK_BF3BEAE2166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO message_project (message_id, project_id) SELECT id, project_id FROM message WHERE project_id IS NOT NULL');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `FK_B6BD307F166D1F9C`');
        $this->addSql('DROP INDEX IDX_B6BD307F166D1F9C ON message');
        $this->addSql('ALTER TABLE message DROP project_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD project_id INT DEFAULT NULL');
        $this->addSql('UPDATE message m SET project_id = (SELECT MIN(mp.project_id) FROM message_project mp WHERE mp.message_id = m.id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `FK_B6BD307F166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B6BD307F166D1F9C ON message (project_id)');
        $this->addSql('ALTER TABLE message_project DROP FOREIGN KEY FK_BF3BEAE2537A1329');
        $this->addSql('ALTER TABLE message_project DROP FOREIGN KEY FK_BF3BEAE2166D1F9C');
        $this->addSql('DROP TABLE message_project');
    }
}
