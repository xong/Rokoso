<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tasks have a due date only: drop their end (also of changed occurrences)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE calendar_item SET ends_at = NULL WHERE type = 'task'");
        $this->addSql("UPDATE calendar_exception e INNER JOIN calendar_item i ON i.id = e.item_id SET e.ends_at = NULL WHERE i.type = 'task'");
    }

    public function down(Schema $schema): void
    {
    }
}
