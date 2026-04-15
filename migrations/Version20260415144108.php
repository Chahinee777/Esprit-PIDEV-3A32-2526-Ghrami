<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415144108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE class_providers CHANGE rating rating DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE users ADD is_two_factor_enabled TINYINT DEFAULT 0 NOT NULL, ADD two_factor_secret VARCHAR(32) DEFAULT NULL, ADD two_factor_enabled_at DATETIME DEFAULT NULL, ADD two_factor_backup_codes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE class_providers CHANGE rating rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE users DROP is_two_factor_enabled, DROP two_factor_secret, DROP two_factor_enabled_at, DROP two_factor_backup_codes');
    }
}
