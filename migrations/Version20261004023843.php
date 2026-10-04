<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004023843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'File versions, public share links, shelf entries for messages and forum topics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file_share (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, downloads INT NOT NULL, file_id INT NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_45852D6D5F37A13B (token), INDEX IDX_45852D6D93CB796C (file_id), INDEX IDX_45852D6DB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE file_version (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, storage_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, file_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_E47A6AF893CB796C (file_id), INDEX IDX_E47A6AF8A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE file_share ADD CONSTRAINT FK_45852D6D93CB796C FOREIGN KEY (file_id) REFERENCES stored_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_share ADD CONSTRAINT FK_45852D6DB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE file_version ADD CONSTRAINT FK_E47A6AF893CB796C FOREIGN KEY (file_id) REFERENCES stored_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_version ADD CONSTRAINT FK_E47A6AF8A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shelf_item ADD message_id INT DEFAULT NULL, ADD topic_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE shelf_item ADD CONSTRAINT FK_97E3B340537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shelf_item ADD CONSTRAINT FK_97E3B3401F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX shelf_owner_message ON shelf_item (owner_id, message_id)');
        $this->addSql('CREATE UNIQUE INDEX shelf_owner_topic ON shelf_item (owner_id, topic_id)');
        $this->addSql('CREATE INDEX IDX_97E3B340537A1329 ON shelf_item (message_id)');
        $this->addSql('CREATE INDEX IDX_97E3B3401F55203D ON shelf_item (topic_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file_share DROP FOREIGN KEY FK_45852D6D93CB796C');
        $this->addSql('ALTER TABLE file_share DROP FOREIGN KEY FK_45852D6DB03A8386');
        $this->addSql('ALTER TABLE file_version DROP FOREIGN KEY FK_E47A6AF893CB796C');
        $this->addSql('ALTER TABLE file_version DROP FOREIGN KEY FK_E47A6AF8A2B28FE8');
        $this->addSql('DROP TABLE file_share');
        $this->addSql('DROP TABLE file_version');
        $this->addSql('ALTER TABLE shelf_item DROP FOREIGN KEY FK_97E3B340537A1329');
        $this->addSql('ALTER TABLE shelf_item DROP FOREIGN KEY FK_97E3B3401F55203D');
        $this->addSql('DROP INDEX shelf_owner_message ON shelf_item');
        $this->addSql('DROP INDEX shelf_owner_topic ON shelf_item');
        $this->addSql('DROP INDEX IDX_97E3B340537A1329 ON shelf_item');
        $this->addSql('DROP INDEX IDX_97E3B3401F55203D ON shelf_item');
        $this->addSql('ALTER TABLE shelf_item DROP message_id, DROP topic_id');
    }
}
