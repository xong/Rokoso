<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002160716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forum boards, topics, posts and uploads';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forum_board (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, project_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_40228D9032C8A3DE (organization_id), INDEX IDX_40228D90166D1F9C (project_id), INDEX IDX_40228D90B03A8386 (created_by_id), INDEX IDX_40228D90727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_post (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, topic_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_996BCC5A1F55203D (topic_id), INDEX IDX_996BCC5AF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_topic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, post_count INT NOT NULL, created_at DATETIME NOT NULL, last_post_at DATETIME NOT NULL, project_id INT DEFAULT NULL, last_post_by_id INT DEFAULT NULL, board_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX forum_topic_last_post (last_post_at), INDEX IDX_853478CC166D1F9C (project_id), INDEX IDX_853478CCD488C601 (last_post_by_id), INDEX IDX_853478CCE7EC5785 (board_id), INDEX IDX_853478CCB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_topic_read (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME NOT NULL, user_id INT NOT NULL, topic_id INT NOT NULL, UNIQUE INDEX forum_read_user_topic (user_id, topic_id), INDEX IDX_228A9D97A76ED395 (user_id), INDEX IDX_228A9D971F55203D (topic_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_upload (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, storage_path VARCHAR(255) NOT NULL, post_id INT DEFAULT NULL, board_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_F12D5CE4B89032C (post_id), INDEX IDX_F12D5CEE7EC5785 (board_id), INDEX IDX_F12D5CEA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE forum_board ADD CONSTRAINT FK_40228D9032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_board ADD CONSTRAINT FK_40228D90166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_board ADD CONSTRAINT FK_40228D90B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_board ADD CONSTRAINT FK_40228D90727ACA70 FOREIGN KEY (parent_id) REFERENCES forum_board (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5A1F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_996BCC5AF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCD488C601 FOREIGN KEY (last_post_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCE7EC5785 FOREIGN KEY (board_id) REFERENCES forum_board (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE forum_topic_read ADD CONSTRAINT FK_228A9D97A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_topic_read ADD CONSTRAINT FK_228A9D971F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_upload ADD CONSTRAINT FK_F12D5CE4B89032C FOREIGN KEY (post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_upload ADD CONSTRAINT FK_F12D5CEE7EC5785 FOREIGN KEY (board_id) REFERENCES forum_board (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_upload ADD CONSTRAINT FK_F12D5CEA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_board DROP FOREIGN KEY FK_40228D9032C8A3DE');
        $this->addSql('ALTER TABLE forum_board DROP FOREIGN KEY FK_40228D90166D1F9C');
        $this->addSql('ALTER TABLE forum_board DROP FOREIGN KEY FK_40228D90B03A8386');
        $this->addSql('ALTER TABLE forum_board DROP FOREIGN KEY FK_40228D90727ACA70');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5A1F55203D');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_996BCC5AF675F31B');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC166D1F9C');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCD488C601');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCE7EC5785');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCB03A8386');
        $this->addSql('ALTER TABLE forum_topic_read DROP FOREIGN KEY FK_228A9D97A76ED395');
        $this->addSql('ALTER TABLE forum_topic_read DROP FOREIGN KEY FK_228A9D971F55203D');
        $this->addSql('ALTER TABLE forum_upload DROP FOREIGN KEY FK_F12D5CE4B89032C');
        $this->addSql('ALTER TABLE forum_upload DROP FOREIGN KEY FK_F12D5CEE7EC5785');
        $this->addSql('ALTER TABLE forum_upload DROP FOREIGN KEY FK_F12D5CEA2B28FE8');
        $this->addSql('DROP TABLE forum_board');
        $this->addSql('DROP TABLE forum_post');
        $this->addSql('DROP TABLE forum_topic');
        $this->addSql('DROP TABLE forum_topic_read');
        $this->addSql('DROP TABLE forum_upload');
    }
}
