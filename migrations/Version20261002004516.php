<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002004516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendar_item (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, all_day TINYINT NOT NULL, done TINYINT NOT NULL, recurrence VARCHAR(20) NOT NULL, recurrence_interval INT NOT NULL, recurrence_until DATE DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT DEFAULT NULL, project_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_4D8DA64A55A0507C (starts_at), INDEX IDX_4D8DA64A32C8A3DE (organization_id), INDEX IDX_4D8DA64A166D1F9C (project_id), INDEX IDX_4D8DA64AB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE calendar_item_assignee (calendar_item_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2FC44C7E70182999 (calendar_item_id), INDEX IDX_2FC44C7EA76ED395 (user_id), PRIMARY KEY (calendar_item_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE calendar_item_participant (calendar_item_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7698909470182999 (calendar_item_id), INDEX IDX_76989094A76ED395 (user_id), PRIMARY KEY (calendar_item_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64AB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item_assignee ADD CONSTRAINT FK_2FC44C7E70182999 FOREIGN KEY (calendar_item_id) REFERENCES calendar_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item_assignee ADD CONSTRAINT FK_2FC44C7EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item_participant ADD CONSTRAINT FK_7698909470182999 FOREIGN KEY (calendar_item_id) REFERENCES calendar_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item_participant ADD CONSTRAINT FK_76989094A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A32C8A3DE');
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A166D1F9C');
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64AB03A8386');
        $this->addSql('ALTER TABLE calendar_item_assignee DROP FOREIGN KEY FK_2FC44C7E70182999');
        $this->addSql('ALTER TABLE calendar_item_assignee DROP FOREIGN KEY FK_2FC44C7EA76ED395');
        $this->addSql('ALTER TABLE calendar_item_participant DROP FOREIGN KEY FK_7698909470182999');
        $this->addSql('ALTER TABLE calendar_item_participant DROP FOREIGN KEY FK_76989094A76ED395');
        $this->addSql('DROP TABLE calendar_item');
        $this->addSql('DROP TABLE calendar_item_assignee');
        $this->addSql('DROP TABLE calendar_item_participant');
    }
}
