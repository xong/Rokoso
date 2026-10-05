<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005164829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Confidential contact: cases, messages, confidants and retention';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE confidential_case (id INT AUTO_INCREMENT NOT NULL, created_on DATE NOT NULL, last_activity_on DATE NOT NULL, closed_on DATE DEFAULT NULL, email LONGTEXT DEFAULT NULL, staff_unread TINYINT NOT NULL, reporter_unread TINYINT NOT NULL, lookup_hash VARCHAR(64) NOT NULL, wrapped_key LONGTEXT NOT NULL, subject LONGTEXT NOT NULL, organization_id INT NOT NULL, UNIQUE INDEX UNIQ_1AE5B8C0E0330F85 (lookup_hash), INDEX IDX_1AE5B8C032C8A3DE77795102 (organization_id, last_activity_on), INDEX IDX_1AE5B8C032C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE confidential_message (id INT AUTO_INCREMENT NOT NULL, created_on DATE NOT NULL, from_staff TINYINT NOT NULL, body LONGTEXT NOT NULL, confidential_case_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_723F85A7B92612D (confidential_case_id), INDEX IDX_723F85A7F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE confidential_case ADD CONSTRAINT FK_1AE5B8C032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE confidential_message ADD CONSTRAINT FK_723F85A7B92612D FOREIGN KEY (confidential_case_id) REFERENCES confidential_case (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE confidential_message ADD CONSTRAINT FK_723F85A7F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE membership ADD confidant TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE organization ADD confidential_retention_months INT DEFAULT 6 NOT NULL');
        $this->addSql('ALTER TABLE public_settings ADD confidential_enabled TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE confidential_case DROP FOREIGN KEY FK_1AE5B8C032C8A3DE');
        $this->addSql('ALTER TABLE confidential_message DROP FOREIGN KEY FK_723F85A7B92612D');
        $this->addSql('ALTER TABLE confidential_message DROP FOREIGN KEY FK_723F85A7F675F31B');
        $this->addSql('DROP TABLE confidential_case');
        $this->addSql('DROP TABLE confidential_message');
        $this->addSql('ALTER TABLE membership DROP confidant');
        $this->addSql('ALTER TABLE organization DROP confidential_retention_months');
        $this->addSql('ALTER TABLE public_settings DROP confidential_enabled');
    }
}
