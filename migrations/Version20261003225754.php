<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003225754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tasks: optional due date, status instead of done flag, source message/topic';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendar_item ADD status VARCHAR(20) DEFAULT \'open\' NOT NULL, ADD source_message_id INT DEFAULT NULL, ADD source_topic_id INT DEFAULT NULL, CHANGE starts_at starts_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE calendar_item SET status = \'done\' WHERE done = 1');
        $this->addSql('ALTER TABLE calendar_item DROP done');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A2FCE620D FOREIGN KEY (source_message_id) REFERENCES message (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_item ADD CONSTRAINT FK_4D8DA64A5C7516EF FOREIGN KEY (source_topic_id) REFERENCES forum_topic (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4D8DA64A2FCE620D ON calendar_item (source_message_id)');
        $this->addSql('CREATE INDEX IDX_4D8DA64A5C7516EF ON calendar_item (source_topic_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A2FCE620D');
        $this->addSql('ALTER TABLE calendar_item DROP FOREIGN KEY FK_4D8DA64A5C7516EF');
        $this->addSql('DROP INDEX IDX_4D8DA64A2FCE620D ON calendar_item');
        $this->addSql('DROP INDEX IDX_4D8DA64A5C7516EF ON calendar_item');
        $this->addSql('ALTER TABLE calendar_item ADD done TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE calendar_item SET done = 1 WHERE status = \'done\'');
        $this->addSql('UPDATE calendar_item SET starts_at = created_at WHERE starts_at IS NULL');
        $this->addSql('ALTER TABLE calendar_item DROP status, DROP source_message_id, DROP source_topic_id, CHANGE starts_at starts_at DATETIME NOT NULL');
    }
}
