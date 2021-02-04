<?php

namespace Outlandish\Wpackagist\Service;

use Composer\Package\Version\VersionParser;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Exception\GuzzleException;
use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Entity\PackageRepository;
use Outlandish\Wpackagist\Entity\Plugin;
use Outlandish\Wpackagist\Entity\Theme;
use Psr\Log\LoggerInterface;
use Rarst\Guzzle\WporgClient;

class Update
{
    /** @var Connection */
    private $connection;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var PackageRepository */
    private $repo;

    public function __construct(Connection $connection,  EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->repo = $entityManager->getRepository(Package::class);
    }

    public function updateAll(LoggerInterface $logger)
    {
        $packages = $this->repo->findDueUpdate();
        $this->update($logger, $packages);
    }

    public function updateOne(LoggerInterface $logger, string $name): ?Package
    {
        $package = $this->repo->findOneBy(['name' => $name]);

        if ($package) {
            $this->update($logger, [$package]);
        }

        return $package;
    }

    /**
     * @param LoggerInterface $logger
     * @param Package[] $packages
     */
    protected function update(LoggerInterface $logger, array $packages)
    {
        $count = count($packages);
        $versionParser = new VersionParser();

        $wporgClient = WporgClient::getClient();

        $logger->info("Updating {$count} packages");

        foreach ($packages as $index => $package) {
            $percent = $index / $count * 100;

            $name = $package->getName();

            $info = null;
            $fields = ['versions'];
            try {
                if ($package instanceof Plugin) {
                    $info = $wporgClient->getPlugin($name, $fields);
                } else {
                    $info = $wporgClient->getTheme($name, $fields);
                }

                $logger->info(sprintf("<info>%04.1f%%</info> Fetched %s %s", $percent, $package->getType(), $name));
            } catch (CommandClientException $exception) {
                $res = $exception->getResponse();
                $this->deactivate($package, $res->getStatusCode() . ': ' . $res->getReasonPhrase(), $logger);
                continue;
            } catch (GuzzleException $exception) {
                $logger->warning("Skipped {$package->getType()} '{$name}' due to error: '{$exception->getMessage()}'");
            }

            if (empty($info)) {
                // Plugin is not active
                $this->deactivate($package, 'not active', $logger);

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
                $package->setLastFetched(new \DateTime());
                $package->setVersions($versions);
                $package->setIsActive(true);
                $package->setDisplayName($info['name']);
                $this->entityManager->persist($package);
            } else {
                // Plugin is not active
                $this->deactivate($package, 'no versions found', $logger);
            }
        }
        $this->entityManager->flush();
    }

    private function deactivate(Package $package, string $reason, LoggerInterface $logger)
    {
        $package->setLastFetched(new \DateTime());
        $package->setIsActive(false);
        $this->entityManager->persist($package);
        $logger->info(sprintf("<info>Deactivated %s %s because %s</info>", $package->getType(), $package->getName(), $reason));
    }
}
