<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add index `class_and_last_committed_idx`
 */
final class Version20231025122432 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index class_and_last_committed_idx';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX class_and_last_committed_idx ON packages (class_name, last_committed)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX class_and_last_committed_idx');
    }
}
