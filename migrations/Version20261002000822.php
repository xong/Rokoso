<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002000822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, organization_id INT NOT NULL, invited_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F11D61A25F37A13B (token), INDEX IDX_F11D61A232C8A3DE (organization_id), INDEX IDX_F11D61A2A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, role VARCHAR(20) NOT NULL, organization_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_86FFD28532C8A3DEA76ED395 (organization_id, user_id), INDEX IDX_86FFD28532C8A3DE (organization_id), INDEX IDX_86FFD285A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, color VARCHAR(7) NOT NULL, logo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A232C8A3DE');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A7B4A7E3');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD28532C8A3DE');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD285A76ED395');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE organization');
    }
}
