<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003224647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, emailed_at DATETIME DEFAULT NULL, type VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, ref_key VARCHAR(120) DEFAULT NULL, recipient_id INT NOT NULL, actor_id INT DEFAULT NULL, INDEX notification_recipient_read (recipient_id, read_at), UNIQUE INDEX notification_ref (recipient_id, ref_key), INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX IDX_BF5476CA10DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_subscription (id INT AUTO_INCREMENT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, endpoint VARCHAR(1000) NOT NULL, public_key VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX push_endpoint (endpoint_hash), INDEX IDX_562830F3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE watch (id INT AUTO_INCREMENT NOT NULL, board_id INT DEFAULT NULL, topic_id INT DEFAULT NULL, project_id INT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX watch_board (user_id, board_id), UNIQUE INDEX watch_topic (user_id, topic_id), UNIQUE INDEX watch_project (user_id, project_id), INDEX IDX_500B4A26E7EC5785 (board_id), INDEX IDX_500B4A261F55203D (topic_id), INDEX IDX_500B4A26166D1F9C (project_id), INDEX IDX_500B4A26A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA10DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch ADD CONSTRAINT FK_500B4A26E7EC5785 FOREIGN KEY (board_id) REFERENCES forum_board (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch ADD CONSTRAINT FK_500B4A261F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch ADD CONSTRAINT FK_500B4A26166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch ADD CONSTRAINT FK_500B4A26A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user ADD notification_email VARCHAR(10) DEFAULT \'instant\' NOT NULL, ADD last_digest_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA10DAF24A');
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY FK_562830F3A76ED395');
        $this->addSql('ALTER TABLE watch DROP FOREIGN KEY FK_500B4A26E7EC5785');
        $this->addSql('ALTER TABLE watch DROP FOREIGN KEY FK_500B4A261F55203D');
        $this->addSql('ALTER TABLE watch DROP FOREIGN KEY FK_500B4A26166D1F9C');
        $this->addSql('ALTER TABLE watch DROP FOREIGN KEY FK_500B4A26A76ED395');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('DROP TABLE watch');
        $this->addSql('ALTER TABLE app_user DROP notification_email, DROP last_digest_at');
    }
}
