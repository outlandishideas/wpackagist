<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\DBAL\Statement;
use GuzzleHttp\Exception\GuzzleException;
use Outlandish\Wpackagist\Package\AbstractPackage;
use Outlandish\Wpackagist\Package\Plugin;
use Outlandish\Wpackagist\Package\Theme;
use Rarst\Guzzle\WporgClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Package\Version\VersionParser;

class UpdateCommand extends DbAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update version info for individual plugins')
            ->addOption(
                'name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Name of package to update',
                null
            )
            ->addOption(
                'concurrent',
                null,
                InputOption::VALUE_REQUIRED,
                'Max concurrent connections',
                '10'
            );
    }

    /**
     * Parse the $version => $tag from the developers tab of wordpress.org
     * Advantages:
     *   * Checks for invalid and inactive plugins (and ignore it until next SVN commit)
     *   * Use the parsing mechanism of wordpress.org, which is more robust
     *
     * Disadvantages:
     *   * Much slower
     *   * Subject to changes without notice
     *
     * Wordpress.org APIs do not list versions history
     * @link http://codex.wordpress.org/WordPress.org_API
     *
     * <li><a itemprop="downloadUrl" href="http://downloads.wordpress.org/plugin/PLUGIN.zip" rel="nofollow">Development Version</a> (<a href="http://plugins.svn.wordpress.org/PLUGIN/trunk" rel="nofollow">svn</a>)</li>
     * <li><a itemprop="downloadUrl" href="http://downloads.wordpress.org/plugin/PLUGIN.VERSION.zip" rel="nofollow">VERSION</a> (<a href="http://plugins.svn.wordpress.org/PLUGIN/TAG" rel="nofollow">svn</a>)</li>
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $update = $this->connection->prepare(
            'UPDATE packages SET
            last_fetched = datetime("now"), versions = :json, is_active = 1, display_name = :display_name
            WHERE class_name = :class_name AND name = :name'
        );
        $deactivate = $this->connection->prepare('UPDATE packages SET last_fetched = datetime("now"), is_active = 0 WHERE class_name = :class_name AND name = :name');

        $name = $input->getOption('name');

        if ($name) {
            $query = $this->connection->prepare('
                SELECT * FROM packages
                WHERE name = :name
            ');
            $query->bindValue("name", $name);
        } else {
            $query = $this->connection->prepare('
                SELECT * FROM packages
                WHERE last_fetched IS NULL
                OR last_fetched < datetime(last_committed, "+2 hours")
                OR (is_active = 0 AND last_committed > date("now", "-90 days") AND last_fetched < datetime("now", "-7 days"))
            ');
        }
        // get packages that have never been fetched or have been updated since last being fetched
        // or that are inactive but have been updated in the past 90 days and haven't been fetched in the past 7 days
        $query->execute();
        $packages = $query->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

        $count = count($packages);
        $versionParser = new VersionParser();


        $wporgClient = WporgClient::getClient();

        foreach ($packages as $index => $package) {

            $percent = $index / $count * 100;

            if ($package instanceof Plugin) {
                try {
                    $info = $wporgClient->getPlugin($package->getName(), ['versions']);
                } catch (GuzzleException $exception) {
                    $output->writeln("<warn>Skipped plugin '{$package->getName()}' due to error: '{$exception->getMessage()}'</warn>");
                }
            } else {
                try {
                    $info = $wporgClient->getTheme($package->getName(), ['versions']);
                } catch (GuzzleException $exception) {
                    $output->writeln("<warn>Skipped theme '{$package->getName()}' due to error: '{$exception->getMessage()}'</warn>");
                }
            }

            $output->writeln(sprintf("<info>%04.1f%%</info> Fetched %s", $percent, $package->getName()));

            if (empty($info)) {
                // Plugin is not active
                $this->deactivate($deactivate, $package, 'not active', $output);

                continue;
            }

            //get versions as [version => url]
            $versions = $info['versions'] ?: [];

            //current version of plugin not present in tags so add it
            if (empty($versions[$info['version']])) {
                //add to front of array
                $versions = array_reverse($versions, true);
                $versions[$info['version']] = 'trunk';
                $versions = array_reverse($versions, true);
            }

            //all plugins have a dev-trunk version
            if ($package instanceof Plugin) {
                unset($versions['trunk']);
                $versions['dev-trunk'] = 'trunk';
            }

            foreach ($versions as $version => $url) {
                try {
                    //make sure versions are parseable by Composer
                    $versionParser->normalize($version);
                    if ($package instanceof Theme) {
                        //themes have different SVN folder structure
                        $versions[$version] = $version;
                    } elseif ($url !== 'trunk') {
                        //add ref to SVN tag
                        $versions[$version] = 'tags/' . $version;
                    } // else do nothing, for 'trunk'.
                } catch (\UnexpectedValueException $e) {
                    //version is invalid
                    unset($versions[$version]);
                }
            }

            if ($versions) {
                $update->execute([
                    ':display_name' => $info['name'],
                    ':class_name' => get_class($package),
                    ':name' => $package->getName(),
                    ':json' => json_encode($versions)
                ]);
            } else {
                // Plugin is not active
                $this->deactivate($deactivate, $package, 'no versions found', $output);
            }
        }

        $stateUpdate = $this->connection->prepare('
            UPDATE state
            SET value = "yes" WHERE key="build_required"
        ');
        $stateUpdate->execute();

        return 0;
    }

    private function deactivate(Statement $statement, AbstractPackage $package, string $reason, OutputInterface $output)
    {
        $statement->execute([':class_name' => get_class($package), ':name' => $package->getName()]);

        $output->writeln(sprintf("<info>Deactivated package %s because %s", $package->getName(), $reason));
    }
}
