<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table live_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE live_session (
            id INT AUTO_INCREMENT NOT NULL,
            started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            max_viewers INT NOT NULL DEFAULT 0,
            total_viewers INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE live_session');
    }
}
