<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004013552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact groups, contact notes, circular letters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, INDEX IDX_40EA54CA32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact_group_member (contact_group_id INT NOT NULL, contact_id INT NOT NULL, INDEX IDX_8FD5109C647145D0 (contact_group_id), INDEX IDX_8FD5109CE7A1254A (contact_id), PRIMARY KEY (contact_group_id, contact_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_group ADD CONSTRAINT FK_40EA54CA32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_group_member ADD CONSTRAINT FK_8FD5109C647145D0 FOREIGN KEY (contact_group_id) REFERENCES contact_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_group_member ADD CONSTRAINT FK_8FD5109CE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526CE7A1254A ON comment (contact_id)');
        $this->addSql('ALTER TABLE draft ADD circular TINYINT NOT NULL');
        $this->addSql('ALTER TABLE message ADD circular TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_group DROP FOREIGN KEY FK_40EA54CA32C8A3DE');
        $this->addSql('ALTER TABLE contact_group_member DROP FOREIGN KEY FK_8FD5109C647145D0');
        $this->addSql('ALTER TABLE contact_group_member DROP FOREIGN KEY FK_8FD5109CE7A1254A');
        $this->addSql('DROP TABLE contact_group');
        $this->addSql('DROP TABLE contact_group_member');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE7A1254A');
        $this->addSql('DROP INDEX IDX_9474526CE7A1254A ON comment');
        $this->addSql('ALTER TABLE comment DROP contact_id');
        $this->addSql('ALTER TABLE draft DROP circular');
        $this->addSql('ALTER TABLE message DROP circular');
    }
}
