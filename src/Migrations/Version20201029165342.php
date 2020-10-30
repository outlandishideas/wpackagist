<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add `package_data`. Drop `state`. Change package class namespace to `Entity`.
 */
final class Version20201029165342 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add `package_data`; drop `state`; change package class namespace to `Entity`';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE package_data (key VARCHAR(200) NOT NULL, value TEXT DEFAULT NULL, PRIMARY KEY(key))');
        $this->addSql('DROP TABLE state');
        $this->addSql('ALTER TABLE packages ALTER class_name TYPE VARCHAR(255)');

        $this->addSql("UPDATE packages SET class_name = replace(class_name, '\\Package\\', '\\Entity\\')");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE state (key VARCHAR(50) NOT NULL, value VARCHAR(50) NOT NULL, PRIMARY KEY(key))');
        $this->addSql('DROP TABLE package_data');
        $this->addSql('ALTER TABLE packages ALTER class_name TYPE VARCHAR(50)');

        $this->addSql("UPDATE packages SET class_name = replace(class_name, '\\Entity\\', '\\Package\\')");
    }
}
