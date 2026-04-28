<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427162348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_stat CHANGE next_live_at next_live_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE live_session CHANGE started_at started_at DATETIME NOT NULL, CHANGE ended_at ended_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD is_alcohol TINYINT(1) DEFAULT 0 NOT NULL, CHANGE extra_images extra_images JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE cumulative_revenue cumulative_revenue DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_stat CHANGE next_live_at next_live_at DATETIME DEFAULT NULL COMMENT \'Date et heure du prochain live programm\'\'e\'');
        $this->addSql('ALTER TABLE live_session CHANGE started_at started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE ended_at ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product DROP is_alcohol, CHANGE extra_images extra_images JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE `user` CHANGE cumulative_revenue cumulative_revenue DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
