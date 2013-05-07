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
				->setDescription('Build package.json from DB');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln("Building packages");

		$basePath = 'web/p/';
		@mkdir($basePath.'wpackagist', 0777, true);
		$versionParser = new VersionParser();

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$groups = $db->query('
			SELECT strftime("%Y", last_committed) AS year, * FROM plugins
			WHERE versions IS NOT NULL
			ORDER BY name
		')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_OBJ);

		$uid = 1; //don't know what this does but composer requires it
		$providerIncludes = array();
		foreach ($groups as $year => $plugins) {
			$providers = array();

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
						'uid' => $uid++
					);
				}

				$content = json_encode(array('packages' => array($packageName => $package)));
				$sha256 = hash('sha256', $content);
				file_put_contents("$basePath$packageName\$$sha256.json", $content);
				$providers["$packageName"] = array(
					'sha256' => $sha256
				);
			}

			$content = json_encode(array('providers' => $providers));
			$sha256 = hash('sha256', $content);
			file_put_contents("{$basePath}providers-$year\$$sha256.json", $content);
			$providerIncludes["p/providers-$year\$%hash%.json"] = array(
				'sha256' => $sha256
			);
			$output->writeln('Generated packages for '.$year);
		}

		$content = json_encode(array(
			'packages' => array(),
			'providers-url' => '/p/%package%$%hash%.json',
			'provider-includes' => $providerIncludes,
		));
		file_put_contents('web/packages.json', $content);

		$output->writeln("Wrote packages.json file");
	}

}