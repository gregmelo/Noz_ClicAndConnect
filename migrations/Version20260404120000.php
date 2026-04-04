<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add next_live_at field to global_stat for scheduling next live date/time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_stat ADD next_live_at DATETIME DEFAULT NULL COMMENT "Date et heure du prochain live programm\'e"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_stat DROP next_live_at');
    }
}
