<?php

namespace Outlandish\Wpackagist;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Outlandish\Wpackagist\Package\Core;

class RefreshCommand extends Command
{
    protected function configure()
    {
        $this
                ->setName('refresh')
                ->setDescription('Refresh list of plugins from WP SVN')
                ->addOption(
                    'svn',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Path to svn executable',
                    'svn'
                );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateCore($input, $output);

        $svn = $input->getOption('svn');

        $types = array(
            'plugin' => 'Outlandish\Wpackagist\Package\Plugin',
            'theme'  => 'Outlandish\Wpackagist\Package\Theme',
        );

        /**
         * @var \PDO $db
         */
        $db = $this->getApplication()->getDb();

        $updateStmt = $db->prepare('UPDATE packages SET last_committed = :date WHERE class_name = :class_name AND name = :name');
        $insertStmt = $db->prepare('INSERT INTO packages (class_name, name, last_committed) VALUES (:class_name, :name, :date)');

        foreach ($types as $type => $class_name) {
            $url = call_user_func(array($class_name, 'getSvnBaseUrl'));
            $output->writeln("Fetching full $type list from $url");

            $xmlLines = array();
            exec("$svn ls --xml $url 2>&1", $xmlLines, $returnCode);
            if ($returnCode) {
                $output->writeln('<error>Error from svn command</error>');

                return 1; //error code
            }
            $xml = simplexml_load_string(implode("\n", $xmlLines));

            $output->writeln("Updating database");

            $db->beginTransaction();
            $newCount = 0;
            foreach ($xml->list->entry as $entry) {
                $date = date('Y-m-d H:i:s', strtotime((string) $entry->commit->date));
                $params = array(':class_name' => $class_name, ':name' => (string) $entry->name, ':date' => $date);

                $updateStmt->execute($params);
                if ($updateStmt->rowCount() == 0) {
                    $insertStmt->execute($params);
                    $newCount++;
                }
            }
            $db->commit();

            $updateCount = $db->query($s = 'SELECT COUNT(*) FROM packages WHERE last_fetched < last_committed AND class_name = ' . $db->quote($class_name))->fetchColumn();

            $output->writeln("Found $newCount new and $updateCount updated {$type}s");
        }
    }

    /**
     * Update Wordpress here as it have a different structure
     */
    protected function updateCore(InputInterface $input, OutputInterface $output)
    {
        $svn = $input->getOption('svn');

        /**
         * @var \PDO $db
         */
        $db = $this->getApplication()->getDb();

        $updateStmt = $db->prepare('UPDATE packages SET last_committed = :date, last_fetched = datetime("now"), versions = :json, is_active = 1 WHERE class_name = :class_name AND name = :name');
        $insertStmt = $db->prepare('INSERT INTO packages (class_name, name, last_committed, last_fetched, versions, is_active) VALUES (:class_name, :name, :date, datetime("now"), :json, 1)');

        $url = Core::getSvnBaseUrl() . 'tags/';
        $output->writeln("Fetching Wordpress core versions from $url");

        $xmlLines = array();
        exec("$svn ls --xml $url 2>&1", $xmlLines, $returnCode);
        if ($returnCode) {
            $output->writeln('<error>Error from svn command</error>');

            return false; //error code
        }
        $xml = simplexml_load_string(implode("\n", $xmlLines));

        $last_committed = 0;
        $versions = array('dev-trunk' => 'trunk');

        foreach ($xml->list->entry as $entry) {
            $time = strtotime((string) $entry->commit->date);
            $last_committed = max($last_committed, $time);
            $version = (string) $entry->name;
            $versions[$version] = $version;
        }

        $date = date('Y-m-d H:i:s', $last_committed);
        $params = array(':class_name' => 'Outlandish\Wpackagist\Package\Core', ':name' => 'wordpress', ':date' => $date, 'json' => json_encode($versions));

        $updateStmt->execute($params);
        if ($updateStmt->rowCount() == 0) {
            $insertStmt->execute($params);
        }
    }

}
