<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125181316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address CHANGE is_default is_default TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Adresse par défaut\'');
        $this->addSql('ALTER TABLE cart_item CHANGE quantity quantity INT DEFAULT 1 NOT NULL COMMENT \'Quantité (min: 1)\'');
        $this->addSql('ALTER TABLE `order` CHANGE order_number order_number VARCHAR(50) NOT NULL COMMENT \'Numéro unique de commande\', CHANGE total_amount total_amount NUMERIC(10, 2) NOT NULL COMMENT \'Montant total TTC en EUR\'');
        $this->addSql('ALTER TABLE order_item CHANGE unit_price unit_price NUMERIC(10, 2) NOT NULL COMMENT \'Prix unitaire historisé\', CHANGE total_price total_price NUMERIC(10, 2) NOT NULL COMMENT \'Prix total historisé\'');
        $this->addSql('ALTER TABLE product CHANGE slug slug VARCHAR(255) NOT NULL COMMENT \'Slug unique du produit\', CHANGE stock stock INT DEFAULT 0 NOT NULL COMMENT \'Quantité en stock\', CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Produit visible sur le site\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address CHANGE is_default is_default TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE cart_item CHANGE quantity quantity INT NOT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE order_number order_number VARCHAR(50) NOT NULL, CHANGE total_amount total_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE order_item CHANGE unit_price unit_price NUMERIC(10, 2) NOT NULL COMMENT \'Prix historisé\', CHANGE total_price total_price NUMERIC(10, 2) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_D34A04AD989D9B62 ON product');
        $this->addSql('ALTER TABLE product CHANGE slug slug VARCHAR(255) NOT NULL, CHANGE stock stock INT DEFAULT 0 NOT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
