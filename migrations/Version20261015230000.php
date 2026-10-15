<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Access for AI agents (tokens, OAuth), switched off in existing organizations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE organization SET disabled_features = JSON_ARRAY_APPEND(disabled_features, '$', 'agents') WHERE JSON_SEARCH(disabled_features, 'one', 'agents') IS NULL");
        $this->addSql('CREATE TABLE agent_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, hint VARCHAR(8) NOT NULL, refresh_hash VARCHAR(64) DEFAULT NULL, expires_at DATETIME DEFAULT NULL, refresh_expires_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, session_stamp VARCHAR(16) NOT NULL, scopes JSON NOT NULL, name VARCHAR(80) NOT NULL, user_id INT NOT NULL, client_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_66998943B3BC57DA (token_hash), UNIQUE INDEX UNIQ_669989437B1870FA (refresh_hash), INDEX IDX_66998943A76ED395 (user_id), INDEX IDX_6699894319EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oauth_client (id INT AUTO_INCREMENT NOT NULL, client_id VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, name VARCHAR(100) NOT NULL, redirect_uris JSON NOT NULL, UNIQUE INDEX UNIQ_AD73274D19EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oauth_code (id INT AUTO_INCREMENT NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, scopes JSON NOT NULL, redirect_uri LONGTEXT NOT NULL, code_challenge VARCHAR(128) NOT NULL, client_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_7C5CF309E7530879 (code_hash), INDEX IDX_7C5CF30919EB6921 (client_id), INDEX IDX_7C5CF309A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agent_token ADD CONSTRAINT FK_66998943A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agent_token ADD CONSTRAINT FK_6699894319EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth_code ADD CONSTRAINT FK_7C5CF30919EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth_code ADD CONSTRAINT FK_7C5CF309A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_item ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE draft ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE facility ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE forum_post ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE message_event ADD agent_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE wiki_revision ADD agent_name VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_token DROP FOREIGN KEY FK_66998943A76ED395');
        $this->addSql('ALTER TABLE agent_token DROP FOREIGN KEY FK_6699894319EB6921');
        $this->addSql('ALTER TABLE oauth_code DROP FOREIGN KEY FK_7C5CF30919EB6921');
        $this->addSql('ALTER TABLE oauth_code DROP FOREIGN KEY FK_7C5CF309A76ED395');
        $this->addSql('DROP TABLE agent_token');
        $this->addSql('DROP TABLE oauth_client');
        $this->addSql('DROP TABLE oauth_code');
        $this->addSql('ALTER TABLE calendar_item DROP agent_name');
        $this->addSql('ALTER TABLE comment DROP agent_name');
        $this->addSql('ALTER TABLE contact DROP agent_name');
        $this->addSql('ALTER TABLE draft DROP agent_name');
        $this->addSql('ALTER TABLE facility DROP agent_name');
        $this->addSql('ALTER TABLE forum_post DROP agent_name');
        $this->addSql('ALTER TABLE message_event DROP agent_name');
        $this->addSql('ALTER TABLE wiki_revision DROP agent_name');
    }
}
