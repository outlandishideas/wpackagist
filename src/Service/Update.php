<?php

namespace Outlandish\Wpackagist\Service;

use Composer\Package\Version\VersionParser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Exception\GuzzleException;
use Outlandish\Wpackagist\Builder;
use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Entity\Plugin;
use Outlandish\Wpackagist\Entity\Theme;
use Psr\Log\AbstractLogger;
use Rarst\Guzzle\WporgClient;

class Update
{
    /** @var Builder */
    private $builder;
    /** @var Connection */
    private $connection;
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(Builder $builder, Connection $connection,  EntityManagerInterface $entityManager)
    {
        $this->builder = $builder;
        $this->connection = $connection;
        $this->entityManager = $entityManager;
    }

    public function update(AbstractLogger $logger, ?string $name = null)
    {
        $updateStmt = $this->connection->prepare(
            'UPDATE packages SET
            last_fetched = NOW(), versions = :json, is_active = true, display_name = :display_name
            WHERE class_name = :class_name AND name = :name'
        );
        $deactivateStmt = $this->connection->prepare('UPDATE packages SET last_fetched = NOW(), is_active = false WHERE class_name = :class_name AND name = :name');

        $repo = $this->entityManager->getRepository(Package::class);
        /** @var Package[] $packages */
        if ($name) {
            $packages = $repo->findBy(['name' => $name]);
        } else {
            $packages = $repo->findDueUpdate();
        }

        $count = count($packages);
        $versionParser = new VersionParser();

        $wporgClient = WporgClient::getClient();

        $logger->info("Updating {$count} packages");

        foreach ($packages as $index => $package) {
            $percent = $index / $count * 100;

            $info = null;
            $fields = ['versions'];
            try {
                if ($package->getType() === 'plugin') {
                    $info = $wporgClient->getPlugin($package->getName(), $fields);
                } else {
                    $info = $wporgClient->getTheme($package->getName(), $fields);
                }

                $logger->info(sprintf("<info>%04.1f%%</info> Fetched %s %s", $percent, $package->getType(), $package->getName()));
            } catch (CommandClientException $exception) {
                $res = $exception->getResponse();
                $this->deactivate($deactivateStmt, $package, $res->getStatusCode() . ': ' . $res->getReasonPhrase(), $logger);
                continue;
            } catch (GuzzleException $exception) {
                $logger->error("Skipped {$package->getType()} '{$package->getName()}' due to error: '{$exception->getMessage()}'");
            }

            if (empty($info)) {
                // Plugin is not active
                $this->deactivate($deactivateStmt, $package, 'not active', $logger);

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
                $updateStmt->execute([
                    ':display_name' => $info['name'],
                    ':class_name' => get_class($package),
                    ':name' => $package->getName(),
                    ':json' => json_encode($versions),
                ]);
            } else {
                // Plugin is not active
                $this->deactivate($deactivateStmt, $package, 'no versions found', $logger);
            }
        }

        // Update just this package synchronously.
        if (!$package) {
            return;
        }

        // Update the package *if* everything we did above left it in an active state with 1+ versions.
        $package = $this->entityManager->getRepository(Package::class)->find($package->getId());
        if (!empty($package->getVersions()) && $package->isActive()) {
            $this->builder->updatePackage($package);
        }
    }

    private function deactivate(Statement $statement, Package $package, string $reason, AbstractLogger $logger)
    {
        $statement->execute([':class_name' => get_class($package), ':name' => $package->getName()]);
        $logger->error(sprintf("<error>Deactivated package %s because %s</error>", $package->getName(), $reason));
    }
}
