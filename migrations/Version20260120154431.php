<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120154431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD stripe_session_id VARCHAR(255) DEFAULT NULL COMMENT \'ID de session Stripe Checkout\', ADD stripe_payment_intent_id VARCHAR(255) DEFAULT NULL COMMENT \'ID PaymentIntent Stripe\', ADD paid_at DATETIME DEFAULT NULL COMMENT \'Date de paiement effectif\', ADD shipping_cost NUMERIC(10, 2) NOT NULL COMMENT \'Frais de livraison TTC en EUR\', ADD shipping_method VARCHAR(50) NOT NULL COMMENT \'Mode de livraison choisi\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP stripe_session_id, DROP stripe_payment_intent_id, DROP paid_at, DROP shipping_cost, DROP shipping_method');
    }
}
