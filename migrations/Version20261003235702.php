<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003235702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Membership position/term/guest projects, project lead and archive, knowledge base pages with revisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE membership_guest_project (membership_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_A707FC971FB354CD (membership_id), INDEX IDX_A707FC97166D1F9C (project_id), PRIMARY KEY (membership_id, project_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, parent_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, organization_id INT NOT NULL, INDEX IDX_94287689727ACA70 (parent_id), INDEX IDX_94287689896DBBDE (updated_by_id), INDEX IDX_9428768932C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_revision (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, author_id INT DEFAULT NULL, page_id INT NOT NULL, INDEX IDX_86A51003F675F31B (author_id), INDEX IDX_86A51003C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE membership_guest_project ADD CONSTRAINT FK_A707FC971FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership_guest_project ADD CONSTRAINT FK_A707FC97166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_94287689727ACA70 FOREIGN KEY (parent_id) REFERENCES wiki_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_94287689896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_9428768932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_revision ADD CONSTRAINT FK_86A51003F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_revision ADD CONSTRAINT FK_86A51003C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD position VARCHAR(80) DEFAULT NULL, ADD term_ends_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD archived_at DATETIME DEFAULT NULL, ADD lead_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE55458D FOREIGN KEY (lead_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE55458D ON project (lead_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_guest_project DROP FOREIGN KEY FK_A707FC971FB354CD');
        $this->addSql('ALTER TABLE membership_guest_project DROP FOREIGN KEY FK_A707FC97166D1F9C');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_94287689727ACA70');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_94287689896DBBDE');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_9428768932C8A3DE');
        $this->addSql('ALTER TABLE wiki_revision DROP FOREIGN KEY FK_86A51003F675F31B');
        $this->addSql('ALTER TABLE wiki_revision DROP FOREIGN KEY FK_86A51003C4663E4');
        $this->addSql('DROP TABLE membership_guest_project');
        $this->addSql('DROP TABLE wiki_page');
        $this->addSql('DROP TABLE wiki_revision');
        $this->addSql('ALTER TABLE membership DROP position, DROP term_ends_on');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE55458D');
        $this->addSql('DROP INDEX IDX_2FB3D0EE55458D ON project');
        $this->addSql('ALTER TABLE project DROP archived_at, DROP lead_id');
    }
}
