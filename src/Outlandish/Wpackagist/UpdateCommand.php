<?php


namespace Outlandish\Wpackagist;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RollingCurl\Request as RollingRequest;
use RollingCurl\RollingCurl;

class UpdateCommand extends Command
{
	protected function configure() {
		$this
				->setName('update')
				->setDescription('Update version info for individual plugins')
				->addOption(
					'concurrent',
					null,
					InputOption::VALUE_REQUIRED,
					'Max concurrent connections',
					'10'
				)->addOption(
					'base',
					null,
					InputOption::VALUE_REQUIRED,
					'Subversion repository base',
					'http://plugins.svn.wordpress.org/'
				);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$rollingCurl = new RollingCurl;
		$rollingCurl->setSimultaneousLimit((int) $input->getOption('concurrent'));
		$base = rtrim($input->getOption('base'), '/') . '/';

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$plugins = $db->query('
			SELECT * FROM plugins
			WHERE last_fetched IS NULL OR last_fetched < last_committed
			ORDER BY last_committed DESC
		')->fetchAll(\PDO::FETCH_OBJ);

		$count = count($plugins);
		$stmt = $db->prepare('UPDATE plugins SET last_fetched = datetime("now"), versions = :json WHERE name = :name');

		$rollingCurl->setCallback(function(RollingRequest $request, RollingCurl $rollingCurl) use ($base, $count, $stmt, $output) {
			// reparse plugin name
			preg_match("!^$base(.+)/tags/$!", $request->getUrl(), $matches);
			$plugin_name = $matches[1];

			$percent = round(count($rollingCurl->getCompletedRequests()) / $count * 100, 1);
			$output->writeln(sprintf("<info>%04.1f%%</info> Fetched %s", $percent, $plugin_name));

			if ($request->getResponseError()) {
				$output->writeln("<error>Error while fetching ".$request->getUrl()."</error>");
				sleep(1); //there was an error so wait a bit and skip this iteration
			}

			// Parses HTML and gets all li items
			$tags_dom = new \DOMDocument('1.0', 'UTF-8');
			$tags_dom->loadHTML($request->getResponseText());
			$tags = array();

			foreach ($tags_dom->getElementsByTagName('li') as $tag) {
				if ((float) $tag->textContent) {
					$tags[] = trim($tag->textContent, ' /');
				}
			}

			// trunk is not listed as a tag, but is always present
			array_unshift($tags, 'trunk');

			$stmt->execute(array(':name' => $plugin_name, ':json' => json_encode($tags)));
		});

		foreach ($plugins as $plugin) {
			$rollingCurl->get("$base{$plugin->name}/tags/");
		}

		$rollingCurl->execute();
	}
}
