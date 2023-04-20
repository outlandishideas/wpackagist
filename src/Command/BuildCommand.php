<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        $this->doBuild(
            allowMoreTries: 1,
            output: $output,
        );

        $this->showProviders($output);

        $interval = $start->diff(new \DateTime());
        $output->writeln("Wrote package data in " . $interval->format('%Hh %Im %Ss'));

        return 0;
    }

    protected function doBuild(int $allowMoreTries, OutputInterface $output)
    {
        $output->writeln('Starting main build...');

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

        /**
         * In rare edge cases this can hit a `UniqueConstraintViolationException` and would previously crash the
         * process. We now allow retries of the whole command if this happens. {@see Database} still logs a
         * warning so we can easily evaluate frequency of these events, without adding log noise if it's
         * recoverable. For now we allow only 1 retry per build command run.
         */
        try {
            $this->storage->persist();
        } catch (UniqueConstraintViolationException $exception) {
            if ($allowMoreTries > 0) {
                $output->writeln('Caught a `UniqueConstraintViolationException` while finalising package data. Retrying...');
                $this->doBuild(
                    allowMoreTries: $allowMoreTries - 1,
                    output: $output,
                );
            } else {
                throw $exception;
            }
        }

        // now all of the packages are up-to-date, rebuild all of the provider groups and the root
        foreach ($providerGroups as $group => $groupPackageNames) {
            $this->builder->updateProviderGroup($group, $groupPackageNames);
        }
        $this->storage->persist();
        $this->builder->updateRoot();

        // final persist, to write everything that needs writing
        $this->storage->persist(true);
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
