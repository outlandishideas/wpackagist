<?php


namespace Outlandish\Wpackagist;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshCommand extends Command {
	protected function configure() {
		$this
				->setName('refresh')
				->setDescription('Refresh list of plugins from WP SVN')
				->addOption(
					'svn',
					null,
					InputOption::VALUE_REQUIRED,
					'Path to svn executable',
					'svn'
				)->addOption(
					'base',
					null,
					InputOption::VALUE_REQUIRED,
					'Subversion repository base',
					'http://plugins.svn.wordpress.org/'
				);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$base = rtrim($input->getOption('base'), '/').'/';
		$svn = $input->getOption('svn');

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$output->writeln("Fetching full plugin list from $base");

		$xmlString = shell_exec("$svn ls --xml $base");
		if ($xmlString === null) {
			return 1; //error code
		}
		$xml = simplexml_load_string($xmlString);

		$output->writeln("Updating database");

		$updateStmt = $db->prepare('UPDATE plugins SET last_committed = :date WHERE name = :name');
		$insertStmt = $db->prepare('INSERT INTO plugins (name, last_committed) VALUES (:name, :date)');
		$db->beginTransaction();
		$newCount = 0;
		$updateCount = 0;
		foreach ($xml->list->entry as $entry) {
			$date = date('Y-m-d H:i:s', strtotime((string)$entry->commit->date));
			$params = array(':name' => (string)$entry->name, ':date' => $date);

			$updateStmt->execute($params);
			if ($updateStmt->rowCount() == 0) {
				$insertStmt->execute($params);
				$newCount++;
			} else {
				$updateCount++;
			}
		}
		$db->commit();

		$output->writeln("Found $newCount new and $updateCount updated plugins");
	}

}