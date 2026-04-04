<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extra_images JSON field to product for multiple images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD extra_images JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP extra_images');
    }
}
