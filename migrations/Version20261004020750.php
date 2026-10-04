<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004020750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Public participation: settings, topics, requests, surveys, event signups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_signup (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, persons INT NOT NULL, item_id INT NOT NULL, INDEX IDX_7C3A69BD126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE public_hit (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, ip_hash VARCHAR(64) NOT NULL, organization_id INT NOT NULL, INDEX IDX_B6BB598BC2DF3E378B8E8428 (ip_hash, created_at), INDEX IDX_B6BB598B32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE public_request (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, kind VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, payload JSON NOT NULL, organization_id INT NOT NULL, UNIQUE INDEX UNIQ_6049D02D5F37A13B (token), INDEX IDX_6049D02D32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE public_settings (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(60) DEFAULT NULL, info_enabled TINYINT NOT NULL, intro LONGTEXT DEFAULT NULL, contact_enabled TINYINT NOT NULL, topics_enabled TINYINT NOT NULL, surveys_enabled TINYINT NOT NULL, events_enabled TINYINT NOT NULL, subscribe_enabled TINYINT NOT NULL, privacy_notice LONGTEXT DEFAULT NULL, spam_invisible TINYINT NOT NULL, spam_min_seconds INT NOT NULL, spam_max_per_hour INT NOT NULL, spam_confirm_email TINYINT NOT NULL, contact_account_id INT DEFAULT NULL, organization_id INT NOT NULL, UNIQUE INDEX UNIQ_C071AC3A989D9B62 (slug), UNIQUE INDEX UNIQ_C071AC3A32C8A3DE (organization_id), INDEX IDX_C071AC3A5CD2062D (contact_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE public_topic (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, project_id INT DEFAULT NULL, assignee_id INT DEFAULT NULL, settings_id INT NOT NULL, INDEX IDX_6CEF611B166D1F9C (project_id), INDEX IDX_6CEF611B59EC7D60 (assignee_id), INDEX IDX_6CEF611B59949888 (settings_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, anonymous TINYINT NOT NULL, listed TINYINT NOT NULL, token VARCHAR(32) NOT NULL, opened_at DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_AD5F9BFC5F37A13B (token), INDEX IDX_AD5F9BFC32C8A3DE (organization_id), INDEX IDX_AD5F9BFCB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey_question (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, type VARCHAR(20) NOT NULL, label VARCHAR(500) NOT NULL, options JSON NOT NULL, required TINYINT NOT NULL, survey_id INT NOT NULL, INDEX IDX_EA000F69B3FE509D (survey_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey_response (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, answers JSON NOT NULL, name VARCHAR(120) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, survey_id INT NOT NULL, INDEX IDX_628C4DDCB3FE509D (survey_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_signup ADD CONSTRAINT FK_7C3A69BD126F525E FOREIGN KEY (item_id) REFERENCES calendar_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_hit ADD CONSTRAINT FK_B6BB598B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_request ADD CONSTRAINT FK_6049D02D32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_settings ADD CONSTRAINT FK_C071AC3A5CD2062D FOREIGN KEY (contact_account_id) REFERENCES mail_account (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE public_settings ADD CONSTRAINT FK_C071AC3A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE public_topic ADD CONSTRAINT FK_6CEF611B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE public_topic ADD CONSTRAINT FK_6CEF611B59EC7D60 FOREIGN KEY (assignee_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE public_topic ADD CONSTRAINT FK_6CEF611B59949888 FOREIGN KEY (settings_id) REFERENCES public_settings (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey ADD CONSTRAINT FK_AD5F9BFC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey ADD CONSTRAINT FK_AD5F9BFCB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_question ADD CONSTRAINT FK_EA000F69B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT FK_628C4DDCB3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item ADD public TINYINT NOT NULL, ADD signup TINYINT NOT NULL, ADD signup_limit INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_group ADD public_subscribe TINYINT NOT NULL');
        $this->addSql('ALTER TABLE resolution ADD public TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_signup DROP FOREIGN KEY FK_7C3A69BD126F525E');
        $this->addSql('ALTER TABLE public_hit DROP FOREIGN KEY FK_B6BB598B32C8A3DE');
        $this->addSql('ALTER TABLE public_request DROP FOREIGN KEY FK_6049D02D32C8A3DE');
        $this->addSql('ALTER TABLE public_settings DROP FOREIGN KEY FK_C071AC3A5CD2062D');
        $this->addSql('ALTER TABLE public_settings DROP FOREIGN KEY FK_C071AC3A32C8A3DE');
        $this->addSql('ALTER TABLE public_topic DROP FOREIGN KEY FK_6CEF611B166D1F9C');
        $this->addSql('ALTER TABLE public_topic DROP FOREIGN KEY FK_6CEF611B59EC7D60');
        $this->addSql('ALTER TABLE public_topic DROP FOREIGN KEY FK_6CEF611B59949888');
        $this->addSql('ALTER TABLE survey DROP FOREIGN KEY FK_AD5F9BFC32C8A3DE');
        $this->addSql('ALTER TABLE survey DROP FOREIGN KEY FK_AD5F9BFCB03A8386');
        $this->addSql('ALTER TABLE survey_question DROP FOREIGN KEY FK_EA000F69B3FE509D');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY FK_628C4DDCB3FE509D');
        $this->addSql('DROP TABLE event_signup');
        $this->addSql('DROP TABLE public_hit');
        $this->addSql('DROP TABLE public_request');
        $this->addSql('DROP TABLE public_settings');
        $this->addSql('DROP TABLE public_topic');
        $this->addSql('DROP TABLE survey');
        $this->addSql('DROP TABLE survey_question');
        $this->addSql('DROP TABLE survey_response');
        $this->addSql('ALTER TABLE calendar_item DROP public, DROP signup, DROP signup_limit');
        $this->addSql('ALTER TABLE contact_group DROP public_subscribe');
        $this->addSql('ALTER TABLE resolution DROP public');
    }
}
