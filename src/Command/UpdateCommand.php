<?php

namespace Outlandish\Wpackagist\Command;

use Outlandish\Wpackagist\Service\Update;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    /** @var Update */
    protected $updateService;

    public function __construct(Update $updateService, $name = null)
    {
        $this->updateService = $updateService;

        parent::__construct($name);
    }

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
        $name = $input->getOption('name');
        $logger = new ConsoleLogger($output);
        if ($name) {
            $this->updateService->updateOne($logger, $name);
        } else {
            $this->updateService->updateAll($logger);
        }

        return 0;
    }
}
