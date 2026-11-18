<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118074809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Privacy policy and imprint of the installation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE legal_text (content LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, document VARCHAR(20) NOT NULL, PRIMARY KEY (document)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE legal_text');
    }
}
