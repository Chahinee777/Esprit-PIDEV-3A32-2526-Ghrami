<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated migration: Convert monetary FLOAT columns to DECIMAL
 * for better precision and to prevent floating-point arithmetic errors
 * 
 * Affected tables:
 * - bookings.total_amount: FLOAT -> DECIMAL(10,2)
 * - classes.price: FLOAT -> DECIMAL(10,2)
 */
final class Version20260505000000_ConvertMoneyFloatToDecimal extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert monetary FLOAT columns to DECIMAL(10,2) for precision and prevent financial discrepancies';
    }

    public function up(Schema $schema): void
    {
        // Backup current values and convert bookings.total_amount
        $this->addSql('
            ALTER TABLE bookings 
            MODIFY COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT "0.00"
        ');

        // Convert classes.price
        $this->addSql('
            ALTER TABLE classes 
            MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT "0.00"
        ');
    }

    public function down(Schema $schema): void
    {
        // Revert bookings.total_amount back to FLOAT
        $this->addSql('
            ALTER TABLE bookings 
            MODIFY COLUMN total_amount FLOAT NOT NULL DEFAULT 0
        ');

        // Revert classes.price back to FLOAT
        $this->addSql('
            ALTER TABLE classes 
            MODIFY COLUMN price FLOAT NOT NULL DEFAULT 0
        ');
    }
}
