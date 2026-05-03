<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add weekly digest opt-in and digest logs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD digest_opted_in TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TABLE digest_logs (digest_log_id BIGINT AUTO_INCREMENT NOT NULL, user_id BIGINT NOT NULL, content LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, channel VARCHAR(20) NOT NULL, opened TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_4405F0BDA76ED395 (user_id), INDEX IDX_4405F0BD2A9A7F63 (sent_at), PRIMARY KEY(digest_log_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE digest_logs ADD CONSTRAINT FK_4405F0BDA76ED395 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE digest_logs DROP FOREIGN KEY FK_4405F0BDA76ED395');
        $this->addSql('DROP TABLE digest_logs');
        $this->addSql('ALTER TABLE users DROP digest_opted_in');
    }
}
