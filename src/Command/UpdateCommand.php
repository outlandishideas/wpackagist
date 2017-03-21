<?php

namespace Outlandish\Wpackagist\Command;

use Outlandish\Wpackagist\Package\Plugin;
use Outlandish\Wpackagist\Package\Theme;
use Rarst\Guzzle\WporgClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Package\Version\VersionParser;

class UpdateCommand extends Command
{
    protected function configure()
    {
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var $db \Doctrine\DBAL\Connection
         */
        $db = $this->getApplication()->getSilexApplication()['db'];

        $update = $db->prepare(
            'UPDATE packages SET last_fetched = datetime("now"), versions = :json, is_active = 1
            WHERE class_name = :class_name AND name = :name'
        );
        $deactivate = $db->prepare('UPDATE packages SET last_fetched = datetime("now"), is_active = 0 WHERE class_name = :class_name AND name = :name');

        // get packages that have never been fetched or have been updated since last being fetched
        // or that are inactive but have been updated in the past 90 days and haven't been fetched in the past 7 days
        $packages = $db->query('
            SELECT * FROM packages
            WHERE last_fetched IS NULL
            OR last_fetched < datetime(last_committed, "+2 hours")
            OR (is_active = 0 AND last_committed > date("now", "-90 days") AND last_fetched < datetime("now", "-7 days"))
        ')->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

        $count = count($packages);
        $versionParser = new VersionParser();


        $wporgClient = WporgClient::getClient();

        foreach ($packages as $index => $package) {

            $percent = $index / $count * 100;
            $output->writeln(sprintf("<info>%04.1f%%</info> Fetched %s", $percent, $package->getName()));

            if ($package instanceof Plugin) {
                $info = $wporgClient->getPlugin($package->getName(), ['versions']);
            } else {
                $info = $wporgClient->getTheme($package->getName(), ['versions']);
            }

            if (!$info) {
                // Plugin is not active
                $deactivate->execute(array(':class_name' => get_class($package), ':name' => $package->getName()));

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
                    } elseif ($url == 'trunk') {
                        //do nothing
                    } else {
                        //add ref to SVN tag
                        $versions[$version] = 'tags/'.$version;
                    }
                } catch (\UnexpectedValueException $e) {
                    //version is invalid
                    unset($versions[$version]);
                }
            }

            if ($versions) {
                $update->execute(
                    array(
                        ':class_name' => get_class($package),
                        ':name' => $package->getName(),
                        ':json' => json_encode($versions)
                    )
                );
            } else {
                $deactivate->execute(array(':class_name' => get_class($package), ':name' => $package->getName()));
            }

        }

    }
}
