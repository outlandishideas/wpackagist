<?php


namespace Outlandish\Wpackagist;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
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

		$fs = new Filesystem();

		$basePath = 'web/p.new/';
		$fs->mkdir($basePath.'wpackagist');
		$fs->mkdir($basePath.'wpackagist-plugin');
		$fs->mkdir($basePath.'wpackagist-theme');

		/**
		 * @var \PDO $db
		 */
		$db = $this->getApplication()->getDb();

		$packages = $db->query('
			SELECT * FROM packages
			WHERE versions IS NOT NULL
			ORDER BY name
		')->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

		$uid = 1; //don't know what this does but composer requires it

		$providers = array();

		foreach ($packages as $package) {
			$packageName = $package->getPackageName();
			$packagesData = $package->getPackages($uid);

			foreach ($packagesData as $packageName => $packageData) {
				$content = json_encode(array('packages' => array($packageName => $packageData)));
				$sha256 = hash('sha256', $content);
				file_put_contents("$basePath$packageName\$$sha256.json", $content);
				$providers[$package->getComposerProviderGroup()][$packageName] = array(
					'sha256' => $sha256
				);
			}
		}

		$providerIncludes = array();
		foreach ($providers as $providerGroup => $providers) {
			$content = json_encode(array('providers' => $providers));
			$sha256 = hash('sha256', $content);
			file_put_contents("{$basePath}providers-$providerGroup\$$sha256.json", $content);

			$providerIncludes["p/providers-$providerGroup\$%hash%.json"] = array(
				'sha256' => $sha256
			);

			$output->writeln('Generated packages for '.$providerGroup);
		}

		$content = json_encode(array(
			'packages' => array(),
			'providers-url' => '/p/%package%$%hash%.json',
			'provider-includes' => $providerIncludes,
		));

		//switch old and new files
		if ($fs->exists('web/p')) {
			$fs->rename('web/p', 'web/p.old');
		}
		$fs->rename($basePath, 'web/p/');
		file_put_contents('web/packages.json', $content);

		//this doesn't work
//		$fs->remove('web/p.old');

		exec('rm -rf web/p.old', $return, $code);

		$output->writeln("Wrote packages.json file");
	}

}