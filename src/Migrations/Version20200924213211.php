<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create initial schema, designed to mirror old SQLite DIY one.
 */
final class Version20200924213211 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create initial schema';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE packages (id SERIAL PRIMARY KEY, class_name VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, last_committed TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_fetched TIMESTAMP(0) WITHOUT TIME ZONE, versions JSON, is_active BOOLEAN NOT NULL, display_name VARCHAR(255))');
        $this->addSql('CREATE INDEX last_committed_idx ON packages (last_committed)');
        $this->addSql('CREATE INDEX last_fetched_idx ON packages (last_fetched)');
        $this->addSql('CREATE UNIQUE INDEX type_and_name_unique ON packages (class_name, name)');
        $this->addSql('CREATE TABLE requests (id SERIAL PRIMARY KEY, ip_address VARCHAR(15) NOT NULL, last_request TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, request_count INT NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7B85D65122FFD58C ON requests (ip_address)');
        $this->addSql('CREATE TABLE state (key VARCHAR(50) NOT NULL, value VARCHAR(50) NOT NULL, PRIMARY KEY(key))');

        // Sly actually-a-fixture to maintain compatibility with the previous Sqlite auto setup.
        $this->addSql("INSERT INTO state (key, value) VALUES ('build_required', '')");
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP TABLE packages');
        $this->addSql('DROP TABLE requests');
        $this->addSql('DROP TABLE state');
    }
}
