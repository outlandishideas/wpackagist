<?php

namespace Outlandish\Wpackagist\Service;

use Composer\Package\Version\VersionParser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Exception\GuzzleException;
use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Entity\PackageRepository;
use Outlandish\Wpackagist\Entity\Plugin;
use Outlandish\Wpackagist\Entity\Theme;
use Outlandish\Wpackagist\Persistence\RetrySafeEntityManager;
use Psr\Log\LoggerInterface;
use Rarst\Guzzle\WporgClient;

class Update
{
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var PackageRepository */
    private $repo;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repo = $entityManager->getRepository(Package::class);
    }

    public function updateAll(LoggerInterface $logger): void
    {
        $packages = $this->repo->findDueUpdate();
        $this->update($logger, $packages[1], $packages[0]);
    }

    /**
     * @param LoggerInterface $logger
     * @param string $name
     * @param int $allowMoreTries   How many more times we may try to complete the update.
     *                              Defaults to 1 and is decremented by 1 on a retry, which
     *                              calls the same method again. The idea is to check the repo
     *                              for a fresh copy to update if it seems like 2 threads have
     *                              tried to work on the same data simultaneously and hit a unique
     *                              lock violation as a result.
     * @return Package|null
     * @throws UniqueConstraintViolationException if a unique lock was violated *and* we have no
     *                                            tries left.
     */
    public function updateOne(LoggerInterface $logger, string $name, int $allowMoreTries = 1): ?Package
    {
        try {
            $package = $this->repo->findOneBy(['name' => $name]);
        } catch (EntityManagerClosed $exception) {
            $logger->warning('EntityManagerClosed on repo find, resetting...');
            if ($this->entityManager instanceof RetrySafeEntityManager) {
                $this->entityManager->resetManager();
                $package = $this->repo->findOneBy(['name' => $name]);
            }
        }

        if ($package) {
            try {
                $this->update($logger, [$package], 1);
            } catch (UniqueConstraintViolationException $exception) {
                if ($allowMoreTries > 0) {
                    return $this->updateOne($logger, $name, $allowMoreTries - 1);
                }

                // Else we are out of tries.
                throw $exception;
            }
        }

        return $package;
    }

    /**
     * @param LoggerInterface $logger
     * @param iterable|Package[] $packages
     * @param int $count
     */
    protected function update(LoggerInterface $logger, mixed $packages, int $count): void
    {
        $versionParser = new VersionParser();

        $wporgClient = WporgClient::getClient();

        $logger->info("Updating {$count} packages");

        $batchSize = 100;

        $i = 0;
        foreach ($packages as $package) {
            $percent = ++$i / $count * 100;

            $name = $package->getName();

            $info = null;
            $fields = ['versions'];
            $deactivateReason = null;
            try {
                if ($package instanceof Plugin) {
                    $info = $wporgClient->getPlugin($name, $fields);
                } else {
                    $info = $wporgClient->getTheme($name, $fields);
                }

                $logger->info(sprintf("<info>%04.1f%%</info> Fetched %s %s", $percent, $package->getType(), $name));
                if (empty($info)) {
                    $deactivateReason = 'not active';
                }
            } catch (CommandClientException $exception) {
                $res = $exception->getResponse();
                $deactivateReason = $res->getStatusCode() . ': ' . $res->getReasonPhrase();
            } catch (GuzzleException $exception) {
                $logger->warning("Skipped {$package->getType()} '{$name}' due to error: '{$exception->getMessage()}'");
            }

            if ($info && !$deactivateReason) {
                $versions = $this->extractVersions($package, $info, $versionParser, $logger);

                if ($versions) {
                    $package->setLastFetched(new \DateTime());
                    $package->setVersions($versions);
                    $package->setIsActive(true);
                    $package->setDisplayName($info['name']);
                    $this->entityManager->persist($package);
                } else {
                    $deactivateReason = 'no versions found';
                }
            }

            if ($deactivateReason) {
                $package->setLastFetched(new \DateTime());
                $package->setIsActive(false);
                $this->entityManager->persist($package);
                $logger->info(sprintf("<info>Deactivated %s %s because %s</info>", $package->getType(), $package->getName(), $deactivateReason));
            }

            if (($i % $batchSize) === 0) {
                $logger->info('---Persisting updated packages---');
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param Package $package
     * @param array $info
     * @param VersionParser $versionParser
     * @param LoggerInterface $logger
     * @return array|mixed
     */
    protected function extractVersions($package, $info, $versionParser, $logger)
    {
        $name = $package->getName();

        //get versions as [version => url]
        $versions = $info['versions'] ?: [];

        //current version of plugin not present in tags so add it
        if (empty($versions[$info['version']])) {
            $logger->info('Adding trunk pseudo-version for ' . $name);

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
                // Version is invalid – we've seen this e.g. with 5 numeric parts.
                $logger->info(sprintf(
                    'Skipping invalid version %s for %s %s',
                    $version,
                    $package->getType(),
                    $name
                ));
                unset($versions[$version]);
            }
        }

        return $versions;
    }
}
