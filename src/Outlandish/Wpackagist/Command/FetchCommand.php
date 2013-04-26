<?php


namespace Outlandish\Wpackagist\Command;


use Composer\Package\Version\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

class FetchCommand extends Command {
	protected function configure() {
		$this
				->setName('fetch')
				->setDescription('Fetch plugins from SVN')
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

		$output->writeln("Fetching full plugin list from $base");

		$dirString = shell_exec("$svn ls $base");
		$dirs = explode("\n", $dirString);

		$output->writeln("Found " . number_format(count($dirs)) . " plugins");

		$dirs = array_slice($dirs, 1000, 10);

		$pluginsVersions = array();
		foreach ($dirs as $index => $dir) {
			$pluginName = substr($dir, 0, -1);
			$percent = floor($index / count($dirs) * 1000) / 10;
			$output->writeln(sprintf("<info>%04.1f%%</info> Fetching %s", $percent, $pluginName));

			$tagsString = shell_exec("$svn ls $base{$dir}tags");
			$tags = explode("\n", $tagsString);

			$pluginsVersions[$pluginName] = array();
			foreach ($tags as $tag) {
				if ($tag) {
					$pluginsVersions[$pluginName][] = substr($tag, 0, -1);
				}
			}

		}

		$output->writeln("Building satis.json");

		$versionParser = new VersionParser();

		$repos = array();
		foreach ($pluginsVersions as $pluginName => $versions) {
			$versions[] = 'trunk';
			foreach ($versions as $version) {
				try {
					$versionParser->normalize($version);
				} catch (UnexpectedValueException $e) {
					continue; //skip plugins with weird version numbers
				}

				$filename = $version == 'trunk' ? $pluginName : $pluginName . '.' . $version;
				$repos[] = array(
					'type' => 'package',
					'package' => array(
						'name' => 'wpackagist/' . $pluginName,
						'version' => $version,
						'dist' => array(
							'url' => "http://downloads.wordpress.org/plugin/$filename.zip",
							'type' => 'zip'
						),
						'homepage' => "http://wordpress.org/extend/plugins/$pluginName",
						'require' => array(
							'composer/installers' => '~1.0'
						),
						'type' => 'wordpress-plugin',
					)
				);
			}
		}

		$satis = array(
			'name' => 'WordPress Packagist',
			'homepage' => 'http://wpackagist.org',
			'repositories' => $repos,
			'require-all' => true
		);

		file_put_contents('data/satis.json', json_encode($satis));

		$output->writeln("Wrote file with " . count($repos) . " packages");

		//trigger Satis build
		$command = $this->getApplication()->find('build');
		$arguments = array(
			'command' => 'build',
			'file' => 'data/satis.json',
			'output-dir' => 'web',
		);

		$input = new ArrayInput($arguments);
		$command->run($input, $output);
	}
}