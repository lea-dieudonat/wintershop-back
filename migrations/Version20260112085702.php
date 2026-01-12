<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112085702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_F5299398551F0F81 ON `order`');
        $this->addSql('ALTER TABLE `order` CHANGE order_number reference VARCHAR(50) NOT NULL COMMENT \'Numéro unique de commande\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5299398AEA34913 ON `order` (reference)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_F5299398AEA34913 ON `order`');
        $this->addSql('ALTER TABLE `order` CHANGE reference order_number VARCHAR(50) NOT NULL COMMENT \'Numéro unique de commande\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5299398551F0F81 ON `order` (order_number)');
    }
}
