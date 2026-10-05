<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005071152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Spam folder and blocked senders';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blocked_sender (id INT AUTO_INCREMENT NOT NULL, address VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_EBBCB5B932C8A3DED4E6F81 (organization_id, address), INDEX IDX_EBBCB5B932C8A3DE (organization_id), INDEX IDX_EBBCB5B9B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blocked_sender ADD CONSTRAINT FK_EBBCB5B932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blocked_sender ADD CONSTRAINT FK_EBBCB5B9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD spam_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blocked_sender DROP FOREIGN KEY FK_EBBCB5B932C8A3DE');
        $this->addSql('ALTER TABLE blocked_sender DROP FOREIGN KEY FK_EBBCB5B9B03A8386');
        $this->addSql('DROP TABLE blocked_sender');
        $this->addSql('ALTER TABLE message DROP spam_at');
    }
}
