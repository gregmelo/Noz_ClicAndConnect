<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403110252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product ADD is_live TINYINT(1) NOT NULL, ADD activated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY `FK_C99DBA75A76ED395`');
        $this->addSql('ALTER TABLE push_subscription CHANGE endpoint endpoint LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_c99dba75a76ed395 ON push_subscription');
        $this->addSql('CREATE INDEX IDX_562830F3A76ED395 ON push_subscription (user_id)');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT `FK_C99DBA75A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user CHANGE cumulative_revenue cumulative_revenue DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP is_live, DROP activated_at');
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY FK_562830F3A76ED395');
        $this->addSql('ALTER TABLE push_subscription CHANGE endpoint endpoint TEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_562830f3a76ed395 ON push_subscription');
        $this->addSql('CREATE INDEX IDX_C99DBA75A76ED395 ON push_subscription (user_id)');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` CHANGE cumulative_revenue cumulative_revenue DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
