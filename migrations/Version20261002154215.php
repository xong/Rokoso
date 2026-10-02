<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002154215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comments on projects';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment ADD project_id INT DEFAULT NULL, CHANGE message_id message_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_9474526C166D1F9C ON comment (project_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C166D1F9C');
        $this->addSql('DROP INDEX IDX_9474526C166D1F9C ON comment');
        $this->addSql('ALTER TABLE comment DROP project_id, CHANGE message_id message_id INT NOT NULL');
    }
}
