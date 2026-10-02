<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002155650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Personal shelf';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shelf_item (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, file_id INT DEFAULT NULL, attachment_id INT DEFAULT NULL, owner_id INT NOT NULL, UNIQUE INDEX shelf_owner_file (owner_id, file_id), UNIQUE INDEX shelf_owner_attachment (owner_id, attachment_id), INDEX IDX_97E3B34093CB796C (file_id), INDEX IDX_97E3B340464E68B (attachment_id), INDEX IDX_97E3B3407E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shelf_item ADD CONSTRAINT FK_97E3B34093CB796C FOREIGN KEY (file_id) REFERENCES stored_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shelf_item ADD CONSTRAINT FK_97E3B340464E68B FOREIGN KEY (attachment_id) REFERENCES attachment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shelf_item ADD CONSTRAINT FK_97E3B3407E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shelf_item DROP FOREIGN KEY FK_97E3B34093CB796C');
        $this->addSql('ALTER TABLE shelf_item DROP FOREIGN KEY FK_97E3B340464E68B');
        $this->addSql('ALTER TABLE shelf_item DROP FOREIGN KEY FK_97E3B3407E3C61F9');
        $this->addSql('DROP TABLE shelf_item');
    }
}
