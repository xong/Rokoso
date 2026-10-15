<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facilities with representative and deputy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE facility (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, number VARCHAR(40) DEFAULT NULL, carrier VARCHAR(160) DEFAULT NULL, district VARCHAR(80) DEFAULT NULL, street VARCHAR(160) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(80) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, reported_on DATE DEFAULT NULL, created_at DATETIME NOT NULL, representative_id INT DEFAULT NULL, deputy_id INT DEFAULT NULL, organization_id INT NOT NULL, INDEX facility_name_idx (organization_id, name), INDEX IDX_105994B2FC3FF006 (representative_id), INDEX IDX_105994B24B6F93BB (deputy_id), INDEX IDX_105994B232C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B2FC3FF006 FOREIGN KEY (representative_id) REFERENCES contact (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B24B6F93BB FOREIGN KEY (deputy_id) REFERENCES contact (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B2FC3FF006');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B24B6F93BB');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B232C8A3DE');
        $this->addSql('DROP TABLE facility');
    }
}
