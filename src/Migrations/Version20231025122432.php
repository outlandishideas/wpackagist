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
        $this->addSql('CREATE INDEX package_class_and_last_committed_idx ON packages (class_name, last_committed)');
        $this->addSql('ALTER INDEX last_committed_idx RENAME TO package_last_committed_idx');
        $this->addSql('ALTER INDEX last_fetched_idx RENAME TO package_last_fetched_idx');
        $this->addSql('ALTER INDEX provider_group_idx RENAME TO package_provider_group_idx');
        $this->addSql('ALTER INDEX type_and_name_unique RENAME TO package_type_and_name_unique');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX package_class_and_last_committed_idx');
        $this->addSql('ALTER INDEX package_last_fetched_idx RENAME TO last_fetched_idx');
        $this->addSql('ALTER INDEX package_last_committed_idx RENAME TO last_committed_idx');
        $this->addSql('ALTER INDEX package_provider_group_idx RENAME TO provider_group_idx');
        $this->addSql('ALTER INDEX package_type_and_name_unique RENAME TO type_and_name_unique');
    }
}
