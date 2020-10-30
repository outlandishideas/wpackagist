<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\ORM\EntityManagerInterface;
use Outlandish\Wpackagist\Builder;
use Outlandish\Wpackagist\Entity\Package;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Helper;
use Outlandish\Wpackagist\Storage;

class BuildCommand extends DbAwareCommand
{
    protected Builder $builder;
    private EntityManagerInterface $entityManager;
    protected Storage\Provider $storage;

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

        $this->storage->prepare();

        $packages = $this->entityManager->getRepository(Package::class)->findActive();

        $uid = 1; // don't know what this does but composer requires it

        $providers = [];

        /** @var Package[] $packages */
        foreach ($packages as $package) {
            $packagesData = $package->getPackages($uid);

            foreach ($packagesData as $packageName => $packageData) {
                $content = json_encode(['packages' => [$packageName => $packageData]]);
                $sha256 = hash('sha256', $content);
                $providers[$this->builder->getComposerProviderGroup($package)][$packageName] = [
                    'sha256' => $sha256,
                ];
                $this->storage->save("p/$packageName\$$sha256.json", $content);
                $this->builder->updatePackage($package);
            }
        }

        $table = new Table($output);
        $table->setHeaders(['provider', 'packages', 'size']);

        $providerIncludes = [];
        foreach ($providers as $providerGroup => $includedProviders) {
            $content = json_encode(['providers' => $includedProviders]);
            $sha256 = hash('sha256', $content);
            $providerIncludes["p/providers-$providerGroup\$%hash%.json"] = [
                'sha256' => $sha256,
            ];

            $table->addRow([
                $providerGroup,
                count($includedProviders),
                // Get size in bytes, without resorting to e.g. filesystem operations.
                // https://stackoverflow.com/a/9718273/2803757
                Helper::formatMemory(mb_strlen($content, '8bit')),
            ]);
        }

        $table->render();

        $this->builder->updateRoot($providerIncludes, true);

        $output->writeln("Wrote packages.json file");

        return 0;
    }
}
