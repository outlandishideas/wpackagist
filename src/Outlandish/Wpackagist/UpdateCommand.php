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
        $rollingCurl = new RollingCurl;
        $rollingCurl->setSimultaneousLimit((int) $input->getOption('concurrent'));

        /**
         * @var \PDO $db
         */
        $db = $this->getApplication()->getDb();
        $stmt = $db->prepare('UPDATE packages SET last_fetched = datetime("now"), versions = :json, is_active = 1 WHERE class_name = :class_name AND name = :name');
        $deactivate = $db->prepare('UPDATE packages SET last_fetched = datetime("now"), is_active = 0 WHERE class_name = :class_name AND name = :name');

	    //get packages that have never been fetched or have been updated since last being fetched
	    //or that are inactive but have been updated in the past 90 days and haven't been fetched in the past 7 days
        $plugins = $db->query('
            SELECT * FROM packages
            WHERE last_fetched IS NULL
            OR last_fetched < last_committed
            OR (is_active = 0 AND last_committed > date("now", "-90 days") AND last_fetched < datetime("now", "-7 days"))
        ')->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

        $count = count($plugins);

        $rollingCurl->setCallback(function (RollingRequest $request, RollingCurl $rollingCurl) use ($count, $stmt, $deactivate, $output) {
            $plugin = $request->getExtraInfo();

            $percent = $rollingCurl->countCompleted() / $count * 100;
            $output->writeln(sprintf("<info>%04.1f%%</info> Fetched %s", $percent, $plugin->getName()));

            if ($request->getResponseErrno()) {
                $output->writeln("<error>Error while fetching ".$request->getUrl(). " (".$request->getResponseError().")"."</error>");

	            return;
            }

            $info = $request->getResponseInfo();
            if ($info['http_code'] != 200) {
                // Plugin is not active
                $deactivate->execute(array(':class_name' => get_class($plugin), ':name' => $plugin->getName()));

                return;
            }

            $dom = new \DOMDocument('1.0', 'UTF-8');
            // WP.org generates some parsing errors, ignore them
            @$dom->loadHTML($request->getResponseText());

            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//div[@id="plugin-info"]//a[contains(., "svn")]');
            $versions = array();

            for ($i=0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $href = rtrim($node->getAttribute('href'), '/');

                if (preg_match('/svn\.wordpress\.org\/[^\/]+\/(.+)$/', $href, $matches)) {
                    $tag = $matches[1];
                } else {
                    continue;
                }

                $download = $xpath->query('../a[contains(@href, ".zip")]', $node);
                if ($download->length) {
                    if (preg_match('/\d+(\.\d+)*/', $download->item(0)->textContent, $matches)) {
                        $version = $matches[0];
                    } elseif (preg_match('/development/i', $download->item(0)->textContent)) {
                        $version = 'dev-trunk';
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }

                $versions[$version] = $tag;

                // Version points directly to trunk
                // Add dev-trunk => trunk to make sure it exists
                if ($tag == 'trunk') {
                    $versions['dev-trunk'] = 'trunk';
                }
            }

            if ($versions) {
                $stmt->execute(array(':class_name' => get_class($plugin), ':name' => $plugin->getName(), ':json' => json_encode($versions)));
            } else {
                $deactivate->execute(array(':class_name' => get_class($plugin), ':name' => $plugin->getName()));
            }

	        //recoup some memory
	        $request->setResponseText(null);
	        $request->setResponseInfo(null);
        });

        foreach ($plugins as $plugin) {
            $request = new RollingRequest($plugin->getHomepageUrl() . 'developers/');
            $request->setExtraInfo($plugin);
            $rollingCurl->add($request);
        }

        $rollingCurl->execute();
    }
}
