<?php


namespace Outlandish\Wpackagist;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RollingCurl\Request as RollingRequest;
use RollingCurl\RollingCurl;
use Outlandish\Wpackagist\Package\Theme;

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
				);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$rollingCurl = new RollingCurl;
		$rollingCurl->setSimultaneousLimit((int) $input->getOption('concurrent'));

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();
		$stmt = $db->prepare('UPDATE packages SET last_fetched = datetime("now"), versions = :json WHERE class_name = :class_name AND name = :name');

		$plugins = $db->query('
			SELECT * FROM packages
			WHERE last_fetched IS NULL OR last_fetched < last_committed
			ORDER BY last_committed DESC
		')->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

		$count = count($plugins);

		$rollingCurl->setCallback(function(RollingRequest $request, RollingCurl $rollingCurl) use ($count, $stmt, $output) {
			$plugin = $request->getExtraInfo();

			$percent = round(count($rollingCurl->getCompletedRequests()) / $count * 100, 1);
			$output->writeln(sprintf("<info>%04.1f%%</info> Fetched %s", $percent, $plugin->getName()));

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

			if (! ($plugin instanceof Theme)) {
				// trunk is not listed as a tag, but is always present
				array_unshift($tags, 'trunk');
			}

			$stmt->execute(array(':class_name' => get_class($plugin), ':name' => $plugin->getName(), ':json' => json_encode($tags)));
		});

		foreach ($plugins as $plugin) {
			$request = new RollingRequest($plugin->getSvnTagsUrl());
			$request->setExtraInfo($plugin);
			$rollingCurl->add($request);
		}

		$rollingCurl->execute();
	}
}
