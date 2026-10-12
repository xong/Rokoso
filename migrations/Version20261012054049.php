<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012054049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Color scheme, design and accent color of the public page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public_settings ADD scheme VARCHAR(10) DEFAULT \'light\' NOT NULL, ADD design VARCHAR(10) DEFAULT \'classic\' NOT NULL, ADD accent_color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public_settings DROP scheme, DROP design, DROP accent_color');
    }
}
