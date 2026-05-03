<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420201212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hidden_posts DROP FOREIGN KEY `FK_HIDDEN_POSTS_POST`');
        $this->addSql('ALTER TABLE hidden_posts DROP FOREIGN KEY `FK_HIDDEN_POSTS_USER`');
        $this->addSql('DROP TABLE hidden_posts');
        $this->addSql('ALTER TABLE class_providers CHANGE rating rating DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE posts ADD is_hidden TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hidden_posts (hidden_post_id BIGINT AUTO_INCREMENT NOT NULL, user_id BIGINT NOT NULL, post_id BIGINT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_HIDDEN_POSTS_USER (user_id), INDEX IDX_HIDDEN_POSTS_POST (post_id), UNIQUE INDEX UNIQ_HIDDEN_POSTS_USER_POST (user_id, post_id), PRIMARY KEY (hidden_post_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE hidden_posts ADD CONSTRAINT `FK_HIDDEN_POSTS_POST` FOREIGN KEY (post_id) REFERENCES posts (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hidden_posts ADD CONSTRAINT `FK_HIDDEN_POSTS_USER` FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE class_providers CHANGE rating rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE posts DROP is_hidden');
    }
}
