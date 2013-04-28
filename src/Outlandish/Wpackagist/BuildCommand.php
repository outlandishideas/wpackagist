<?php


namespace Outlandish\Wpackagist;


use Composer\Package\Version\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

class BuildCommand extends Command
{
	protected function configure() {
		$this
				->setName('build')
				->setDescription('Build satis.json from DB');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln("Building packages");

		$versionParser = new VersionParser();

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$groups = $db->query('
			SELECT strftime("%Y-%m", last_committed) AS month, * FROM plugins
			WHERE versions IS NOT NULL
			ORDER BY month, name
		')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_OBJ);

		$includes = array();
		foreach ($groups as $month => $plugins) {
			$packages = array();

			foreach ($plugins as $plugin) {
				$versions = json_decode($plugin->versions);
				$packageName = 'wpackagist/' . $plugin->name;

				$package = array();
				foreach ($versions as $version) {
					try {
						$normalizedVersion = $versionParser->normalize($version);
					} catch (UnexpectedValueException $e) {
						continue; //skip plugins with weird version numbers
					}

					$filename = $version == 'trunk' ? $plugin->name : $plugin->name . '.' . $version;
					$package[$version] = array(
						'name' => $packageName,
						'version' => $version,
						'version_normalized' => $normalizedVersion,
						'dist' => array(
							'type' => 'zip',
							'url' => "http://downloads.wordpress.org/plugin/$filename.zip",
							'reference' => null,
							'shasum' => null
						),
						'require' => array(
							'composer/installers' => '~1.0'
						),
						'type' => 'wordpress-plugin',
						'homepage' => "http://wordpress.org/extend/plugins/$plugin->name",
					);
				}

				$packages[$packageName] = $package;
			}

			$content = json_encode(array('packages' => $packages));
			file_put_contents("web/packages-$month.json", $content);
			$includes["packages-$month.json"] = array(
				'sha1' => sha1($content)
			);
		}

		$content = json_encode(array('includes' => $includes));
		file_put_contents('web/packages.json', $content);

		$output->writeln("Wrote packages.json file");
	}

}