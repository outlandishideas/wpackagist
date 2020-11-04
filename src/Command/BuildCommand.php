<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\ORM\EntityManagerInterface;
use Outlandish\Wpackagist\Builder;
use Outlandish\Wpackagist\Entity\Package;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Helper;
use Outlandish\Wpackagist\Storage;

class BuildCommand extends DbAwareCommand
{
    /** @var Builder */
    protected $builder;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var Storage\Provider */
    protected $storage;

    public function __construct(
        Builder $builder,
        EntityManagerInterface $entityManager,
        Storage\Provider $storage,
        $name = null
    )
    {
        $this->builder = $builder;
        $this->entityManager = $entityManager;
        $this->storage = $storage;

        parent::__construct($entityManager->getConnection(), $name);
    }

    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Build packages.json from DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Building packages");

        $start = new \DateTime();
        $this->storage->prepare();

        $packages = $this->entityManager->getRepository(Package::class)->findActive();

        $uid = 1; // don't know what this does but composer requires it

        $providerGroups = [];

        $progressBar = new ProgressBar($output, count($packages));

        /** @var Package[] $packages */
        foreach ($packages as $package) {
            $packagesData = $package->getPackages($uid);

            foreach ($packagesData as $packageName => $packageData) {
                $content = json_encode(['packages' => [$packageName => $packageData]]);
                $sha256 = hash('sha256', $content);
                $providerGroups[$this->builder->getComposerProviderGroup($package)][$packageName] = [
                    'sha256' => $sha256,
                ];
                $this->storage->save("p/$packageName\$$sha256.json", $content);
                $this->builder->updatePackage($package);
            }
            $progressBar->advance();
        }

        $progressBar->finish();

        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['provider', 'packages', 'size']);

        $providerIncludes = [];
        $totalSize = 0;
        $totalProviders = 0;
        foreach ($providerGroups as $providerGroup => $providers) {
            $content = json_encode(['providers' => $providers]);
            $sha256 = hash('sha256', $content);
            $providerIncludes["p/providers-$providerGroup\$%hash%.json"] = [
                'sha256' => $sha256,
            ];

            // Get size in bytes, without resorting to e.g. filesystem operations.
            // https://stackoverflow.com/a/9718273/2803757
            $filesize = mb_strlen($content, '8bit');
            $totalSize += $filesize;
            $totalProviders += count($providers);
            $table->addRow([
                $providerGroup,
                count($providers),
                Helper::formatMemory($filesize),
            ]);
        }

        $table->addRow(new TableSeparator());
        $table->addRow([
            'Total',
            $totalProviders,
            Helper::formatMemory($totalSize),
        ]);

        $table->render();

        $this->builder->updateRoot($providerIncludes, true);

        $output->writeln('Finalising package data...');
        $this->storage->finalise();

        $interval = $start->diff(new \DateTime());
        $output->writeln("Wrote package data in " . $interval->format('%Hh %Im %Ss'));

        return 0;
    }
}
