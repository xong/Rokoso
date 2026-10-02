<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002004001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, salutation VARCHAR(20) DEFAULT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, company VARCHAR(150) DEFAULT NULL, position VARCHAR(150) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, email2 VARCHAR(180) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, mobile VARCHAR(50) DEFAULT NULL, street VARCHAR(150) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, birthday DATE DEFAULT NULL, tags VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_4C62E638E7927C74 (email), INDEX IDX_4C62E63832C8A3DE (organization_id), INDEX IDX_4C62E638B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63832C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63832C8A3DE');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E638B03A8386');
        $this->addSql('DROP TABLE contact');
    }
}
