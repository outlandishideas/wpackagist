<?php


namespace Outlandish\Wpackagist;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
	protected function configure() {
		$this
				->setName('update')
				->setDescription('Update version info for individual plugins')
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
		$base = rtrim($input->getOption('base'), '/') . '/';
		$svn = $input->getOption('svn');

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$plugins = $db->query('
			SELECT * FROM plugins
			WHERE last_fetched IS NULL OR last_fetched < last_committed
			ORDER BY last_committed DESC
		')->fetchAll(\PDO::FETCH_OBJ);

		foreach ($plugins as $index => $plugin) {
			$percent = floor($index / count($plugins) * 1000) / 10;
			$output->writeln(sprintf("<info>%04.1f%%</info> Fetching %s", $percent, $plugin->name));

			$tagsString = shell_exec("$svn ls $base{$plugin->name}/tags");
			$tags = $tagsString ? explode("\n", trim($tagsString)) : array();
			$tags[] = 'trunk/';
			$tags = array_map(function ($tag) {
				return substr($tag, 0, -1);
			}, $tags);

			$stmt = $db->prepare('UPDATE plugins SET last_fetched = datetime("now"), versions = :json WHERE name = :name');
			$plugin->versions = json_encode($tags);
			$stmt->execute(array(':name' => $plugin->name, ':json' => $plugin->versions));
		}

	}

}