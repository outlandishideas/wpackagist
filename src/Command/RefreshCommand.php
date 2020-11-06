<?php

namespace Outlandish\Wpackagist\Command;

use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Entity\Plugin;
use Outlandish\Wpackagist\Entity\Theme;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshCommand extends DbAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('refresh')
            ->setDescription('Refresh list of plugins and themes from WP SVN')
            ->addOption(
                'svn',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to svn executable',
                'svn'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $svn = $input->getOption('svn');

        $types = [
            'plugin' => Plugin::class,
            'theme'  => Theme::class,
        ];

        $updateStmt = $this->connection->prepare('UPDATE packages SET last_committed = :date, provider_group = :group WHERE class_name = :class_name AND name = :name');
        $insertStmt = $this->connection->prepare('INSERT INTO packages (class_name, name, last_committed, provider_group, is_active) VALUES (:class_name, :name, :date, :group, true)');

        foreach ($types as $type => $class_name) {
            /** @var Plugin|Theme $class_name */
            $url = $class_name::getSvnBaseUrl();
            $output->writeln("Fetching full $type list from $url");

            $xmlLines = [];
            exec("$svn ls --xml $url 2>&1", $xmlLines, $returnCode);
            if ($returnCode > 0) {
                $output->writeln("<error>Error code $returnCode from svn command</error>");

                return $returnCode; // error code
            }
            $xml = simplexml_load_string(implode("\n", $xmlLines));

            $output->writeln("Updating database");

            $this->connection->beginTransaction();
            $newCount = 0;
            foreach ($xml->list->entry as $entry) {
                $date = date('Y-m-d H:i:s', strtotime((string) $entry->commit->date));
                $params = [
                    ':class_name' => $class_name,
                    ':name' => (string) $entry->name,
                    ':date' => $date,
                    ':group' => Package::makeComposerProviderGroup($date)
                ];

                $updateStmt->execute($params);
                if ($updateStmt->rowCount() == 0) {
                    $insertStmt->execute($params);
                    $newCount++;
                }
            }
            $this->connection->commit();

            $updateCount = $this->connection->query($s = 'SELECT COUNT(*) FROM packages WHERE last_fetched < last_committed AND class_name = '.$this->connection->quote($class_name))->fetchColumn();

            $output->writeln("Found $newCount new and $updateCount updated {$type}s");
        }

        return 0;
    }
}
