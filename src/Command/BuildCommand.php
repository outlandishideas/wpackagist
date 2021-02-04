<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\ORM\EntityManagerInterface;
use Outlandish\Wpackagist\Entity\PackageRepository;
use Outlandish\Wpackagist\Service\Builder;
use Outlandish\Wpackagist\Entity\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Helper;
use Outlandish\Wpackagist\Storage;

class BuildCommand extends Command
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

        parent::__construct($name);
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

        // ensure all packages have the right provider group assigned
        $packageRepo->updateProviderGroups();

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

        // now all of the packages are up-to-date, rebuild all of the provider groups and the root
        foreach ($providerGroups as $group => $groupPackageNames) {
            $this->builder->updateProviderGroup($group, $groupPackageNames);
        }
        $this->storage->persist();
        $this->builder->updateRoot();

        // final persist, to write everything that needs writing
        $this->storage->persist(true);

        $this->showProviders($output);

        $interval = $start->diff(new \DateTime());
        $output->writeln("Wrote package data in " . $interval->format('%Hh %Im %Ss'));

        return 0;
    }

    /**
     * @param OutputInterface $output
     */
    protected function showProviders(OutputInterface $output)
    {
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
    }
}
