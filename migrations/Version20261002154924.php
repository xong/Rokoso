<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002154924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Folders and stored files';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE folder (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, project_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_ECA209CD32C8A3DE (organization_id), INDEX IDX_ECA209CD166D1F9C (project_id), INDEX IDX_ECA209CDB03A8386 (created_by_id), INDEX IDX_ECA209CD727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stored_file (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, storage_path VARCHAR(255) NOT NULL, project_id INT DEFAULT NULL, folder_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_C339E77C166D1F9C (project_id), INDEX IDX_C339E77C162CB942 (folder_id), INDEX IDX_C339E77CA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CD32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CD166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CDB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CD727ACA70 FOREIGN KEY (parent_id) REFERENCES folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stored_file ADD CONSTRAINT FK_C339E77C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stored_file ADD CONSTRAINT FK_C339E77C162CB942 FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stored_file ADD CONSTRAINT FK_C339E77CA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comment ADD file_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C93CB796C FOREIGN KEY (file_id) REFERENCES stored_file (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526C93CB796C ON comment (file_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE folder DROP FOREIGN KEY FK_ECA209CD32C8A3DE');
        $this->addSql('ALTER TABLE folder DROP FOREIGN KEY FK_ECA209CD166D1F9C');
        $this->addSql('ALTER TABLE folder DROP FOREIGN KEY FK_ECA209CDB03A8386');
        $this->addSql('ALTER TABLE folder DROP FOREIGN KEY FK_ECA209CD727ACA70');
        $this->addSql('ALTER TABLE stored_file DROP FOREIGN KEY FK_C339E77C166D1F9C');
        $this->addSql('ALTER TABLE stored_file DROP FOREIGN KEY FK_C339E77C162CB942');
        $this->addSql('ALTER TABLE stored_file DROP FOREIGN KEY FK_C339E77CA2B28FE8');
        $this->addSql('DROP TABLE folder');
        $this->addSql('DROP TABLE stored_file');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C93CB796C');
        $this->addSql('DROP INDEX IDX_9474526C93CB796C ON comment');
        $this->addSql('ALTER TABLE comment DROP file_id');
    }
}
