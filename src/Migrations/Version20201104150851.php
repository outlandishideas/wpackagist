<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201104150851 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // add a provider_group column, and populate with appropriate groups, based on the last committed date
        $this->addSql('ALTER TABLE packages ADD provider_group VARCHAR(255) NOT NULL default \'old\'');
        $this->addSql('CREATE INDEX provider_group_idx ON packages (provider_group)');
        $this->addSql('CREATE INDEX package_is_active_idx ON packages (is_active)');
        $year = date('Y');
        $groups = [
            'this-week' => new \DateTime('monday last week'),
            $year . '-12' => new \DateTime($year . '-10-01'),
            $year . '-09' => new \DateTime($year . '-07-01'),
            $year . '-06' => new \DateTime($year . '-04-01'),
            $year . '-03' => new \DateTime($year . '-01-01'),
        ];
        for ($y=$year-1; $y>=2011; $y--) {
            $groups[$y] = new \DateTime($y . '-01-01');
        }
        foreach ($groups as $key=>$date) {
            $this->addSql(
                'UPDATE packages SET provider_group = :group WHERE provider_group = \'old\' AND last_committed >= :date',
                ['group' => $key, 'date' => $date->format('Y-m-d')]
            );
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE packages DROP provider_group');
        $this->addSql('DROP INDEX package_is_active_idx');
    }
}
