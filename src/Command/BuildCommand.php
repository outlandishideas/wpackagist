<?php

namespace Outlandish\Wpackagist\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Helper;
use Outlandish\Wpackagist\Package\AbstractPackage;

class BuildCommand extends DbAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Build packages.json from DB')
            ->addOption('force', null, InputOption::VALUE_NONE);
    }

    /**
     * Return a string to split packages in more-or-less even groups
     * of their last modification. Minimizes groups modifications.
     *
     * @return string
     */
    protected function getComposerProviderGroup(AbstractPackage $package)
    {
        $date = $package->getLastCommited();

        if ($date >= new \DateTime('monday last week')) {
            return 'this-week';
        } elseif ($date >= new \DateTime(date('Y') . '-01-01')) {
            // split current by chunks of 3 months, current month included
            // past chunks will never be update this year
            $month = $date->format('n');
            $month = ceil($month / 3) * 3;
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);

            return $date->format('Y-') . $month;
        } elseif ($date >= new \DateTime('2011-01-01')) {
            // split by years, limit at 2011 so we never update 'old' again
            return $date->format('Y');
        } else {
            // 2010 and older is about 2M, which is manageable
            // Still some packages ? Probably junk/errors
            return 'old';
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('force')) {
            $state = $this->connection->query('
                SELECT value FROM state WHERE key="build_required"
            ')->fetch();

            if (!$state['value']) {
                $output->writeln("Not building packages as build_required was falsey");
                return 1;
            }
        }

        $output->writeln("Building packages");

        $fs = new Filesystem();

        $webPath = __DIR__ . '/../../web/';
        $basePath = $webPath . 'p.new/';
        $fs->mkdir($basePath . 'wpackagist');
        $fs->mkdir($basePath . 'wpackagist-plugin');
        $fs->mkdir($basePath . 'wpackagist-theme');


        $packages = $this->connection->query('
            SELECT * FROM packages
            WHERE versions IS NOT NULL AND is_active
            ORDER BY name
        ')->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE);

        $uid = 1; // don't know what this does but composer requires it

        $providers = [];

        foreach ($packages as $package) {
            $packagesData = $package->getPackages($uid);

            foreach ($packagesData as $packageName => $packageData) {
                $content = json_encode(['packages' => [$packageName => $packageData]]);
                $sha256 = hash('sha256', $content);
                file_put_contents("$basePath$packageName\$$sha256.json", $content);
                $providers[$this->getComposerProviderGroup($package)][$packageName] = [
                    'sha256' => $sha256,
                ];
            }
        }

        $table = new Table($output);
        $table->setHeaders(['provider', 'packages', 'size']);

        $providerIncludes = [];
        foreach ($providers as $providerGroup => $providers) {
            $content = json_encode(['providers' => $providers]);
            $sha256 = hash('sha256', $content);
            file_put_contents("{$basePath}providers-$providerGroup\$$sha256.json", $content);

            $providerIncludes["p/providers-$providerGroup\$%hash%.json"] = [
                'sha256' => $sha256,
            ];

            $table->addRow([
                $providerGroup,
                count($providers),
                Helper::formatMemory(filesize("{$basePath}providers-$providerGroup\$$sha256.json")),
            ]);
        }

        $table->render();

        $content = json_encode([
            'packages' => [],
            'providers-url' => '/p/%package%$%hash%.json',
            'provider-includes' => $providerIncludes,
        ]);

        // switch old and new files
        $originalPath = $webPath . 'p';
        $oldPath = $webPath . 'p.old';
        if ($fs->exists($originalPath)) {
            $fs->rename($originalPath, $oldPath);
        }
        $fs->rename($basePath, $originalPath . '/');

        $packagesPath = $webPath . 'packages.json';
        file_put_contents($packagesPath, $content);

        // this doesn't work
        // $fs->remove('web/p.old');

        exec('rm -rf ' . $oldPath, $return, $code);

        $stateUpdate = $this->connection->prepare('
            UPDATE state
            SET value = "" WHERE key="build_required"
        ');
        $stateUpdate->execute();

        $output->writeln("Wrote packages.json file");

        return 0;
    }
}
