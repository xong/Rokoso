<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008080311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Personal message status (done for me, Wiedervorlage); running snoozes go to whoever set them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_user_state (id INT AUTO_INCREMENT NOT NULL, done_at DATETIME DEFAULT NULL, snoozed_until DATETIME DEFAULT NULL, message_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8E81053C537A1329A76ED395 (message_id, user_id), INDEX IDX_8E81053C537A1329 (message_id), INDEX IDX_8E81053CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message_user_state ADD CONSTRAINT FK_8E81053C537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_user_state ADD CONSTRAINT FK_8E81053CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        // A running Wiedervorlage belongs to the person who set it last (from the history)
        $this->addSql("INSERT INTO message_user_state (message_id, user_id, snoozed_until)
            SELECT m.id, e.user_id, m.snoozed_until FROM message m
            JOIN message_event e ON e.id = (SELECT MAX(e2.id) FROM message_event e2 WHERE e2.message_id = m.id AND e2.type = 'snoozed' AND e2.user_id IS NOT NULL)
            WHERE m.snoozed_until > NOW()");
        $this->addSql('ALTER TABLE message DROP snoozed_until');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD snoozed_until DATETIME DEFAULT NULL');
        $this->addSql('UPDATE message m SET m.snoozed_until = (SELECT MAX(s.snoozed_until) FROM message_user_state s WHERE s.message_id = m.id)');
        $this->addSql('ALTER TABLE message_user_state DROP FOREIGN KEY FK_8E81053C537A1329');
        $this->addSql('ALTER TABLE message_user_state DROP FOREIGN KEY FK_8E81053CA76ED395');
        $this->addSql('DROP TABLE message_user_state');
    }
}
