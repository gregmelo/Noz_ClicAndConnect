<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251217120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create GlobalStat table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE global_stat (id INT AUTO_INCREMENT NOT NULL, total_revenue DOUBLE PRECISION NOT NULL, total_collected_count INT NOT NULL, total_expired_count INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // Insert initial row
        $this->addSql('INSERT INTO global_stat (total_revenue, total_collected_count, total_expired_count) VALUES (0, 0, 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE global_stat');
    }
}
