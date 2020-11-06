<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\ORM\EntityManagerInterface;
use Outlandish\Wpackagist\Entity\PackageRepository;
use Outlandish\Wpackagist\Service\Builder;
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
    /** @var Storage\PackageStore */
    protected $storage;

    public function __construct(
        Builder $builder,
        EntityManagerInterface $entityManager,
        Storage\PackageStore $storage,
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

        /** @var PackageRepository $packageRepo */
        $packageRepo = $this->entityManager->getRepository(Package::class);
        $packages = $packageRepo->findActive();
        // once we have the packages, we don't need them to be tracked any more
        $this->entityManager->clear();

        $this->storage->prepare();

        $providerGroups = [];

        $progressBar = new ProgressBar($output, count($packages));

        foreach ($packages as $package) {
            $this->builder->updatePackage($package);
            $progressBar->advance();
            $group = $package->getProviderGroup();
            if (!array_key_exists($group, $providerGroups)) {
                $providerGroups[$group] = [];
            }
            $providerGroups[$group][] = $package->getPackageName();
        }

        $progressBar->finish();

        $output->writeln('');

        $output->writeln('Finalising package data...');

        $this->storage->persist();
        foreach ($providerGroups as $group => $groupPackageNames) {
            $this->builder->updateProviderGroup($group, $groupPackageNames);
        }
        $this->storage->persist();
        $this->builder->updateRoot();

        $this->storage->persist(true);

        $groups = $this->storage->loadAllProviders();
        ksort($groups);

        $table = new Table($output);
        $table->setHeaders(['provider', 'packages', 'size']);

        $totalSize = 0;
        $totalProviders = 0;
        foreach ($groups as $group => $content) {
            $json = json_decode($content);

            // Get size in bytes, without resorting to e.g. filesystem operations.
            // https://stackoverflow.com/a/9718273/2803757
            $count = count((array)$json->providers);
            $filesize = mb_strlen($content, '8bit');
            $totalSize += $filesize;
            $totalProviders += $count;
            $table->addRow([
                $group,
                $count,
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

        $interval = $start->diff(new \DateTime());
        $output->writeln("Wrote package data in " . $interval->format('%Hh %Im %Ss'));

        return 0;
    }
}
