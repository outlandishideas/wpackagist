<?php

namespace Outlandish\Wpackagist;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Outlandish\Wpackagist\Package\AbstractPackage;

class BuildCommand extends Command
{
    protected function configure()
    {
        $this
                ->setName('build')
                ->setDescription('Build package.json from DB');
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
            WHERE versions IS NOT NULL AND is_active
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
                $providers[$this->getComposerProviderGroup($package)][$packageName] = array(
                    'sha256' => $sha256
                );
            }
        }

        $table = $this->getHelper('table');
        $table->setHeaders(array('provider', 'packages', 'size'));

        $providerIncludes = array();
        foreach ($providers as $providerGroup => $providers) {
            $content = json_encode(array('providers' => $providers));
            $sha256 = hash('sha256', $content);
            file_put_contents("{$basePath}providers-$providerGroup\$$sha256.json", $content);

            $providerIncludes["p/providers-$providerGroup\$%hash%.json"] = array(
                'sha256' => $sha256
            );

            $table->addRow(array(
                $providerGroup,
                count($providers),
                round(filesize("{$basePath}providers-$providerGroup\$$sha256.json") / 1024)
            ));
        }

        $table->render($output);

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
