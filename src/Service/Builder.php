<?php

namespace Outlandish\Wpackagist\Service;

use Doctrine\ORM\EntityManagerInterface;
use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Storage\PackageStore;

class Builder
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var PackageStore */
    private $storage;

    public function __construct(EntityManagerInterface $entityManager, PackageStore $storage)
    {
        $this->entityManager = $entityManager;
        $this->storage = $storage;
    }

    /**
     * Doesn't prepare or finalise storage – call these methods once before and
     * after calling this on all packages if updating many.
     *
     * @param Package $package
     */
    public function updatePackage(Package $package): void
    {
        $packageName = $package->getPackageName();

        $content = json_encode(['packages' => [$packageName => $package->getVersionData()]]);
        $packageSha256 = hash('sha256', $content);
        $this->storage->savePackage($packageName, $packageSha256, $content);
    }

    /**
     * @param string $providerGroupName
     * @param string[] $packageNames
     */
    public function updateProviderGroup(string $providerGroupName, array $packageNames): void
    {
        $packagesJson = $this->storage->loadAllPackages($packageNames);
        $providerJson = [];
        foreach ($packagesJson as $packageName => $packageJson) {
            $sha256 = hash('sha256', $packageJson);
            $providerJson[$packageName] = ['sha256' => $sha256];
        }
        ksort($providerJson);
        $providerDataJson = json_encode(['providers' => $providerJson]);
        $providersSha256 = hash('sha256', $providerDataJson);
        $this->storage->saveProvider("providers-$providerGroupName", $providersSha256, $providerDataJson);
    }

    public function updateRoot(): void
    {
        $providers = $this->storage->loadAllProviders();
        $includes = [];
        $providerFormat = 'p/%package%$%hash%.json';
        foreach ($providers as $name => $value) {
            $sha256 = hash('sha256', $value);

            // Skip/delete old providers, e.g. 3-monthly groups for past years,
            // allowing grouping to smoothly switch over at the start of a new year.
            // Packages themselves automatically update provider group e.g. from '2020-12'
            // to '2020' when the next year starts. `continue`ing here implicitly removes
            // items from `root` while `deactivateProvider()` deletes or deactivates the
            // individual provider.
            if ($this->providerIsOutdated($name)) {
                $this->storage->deactivateProvider($name, $sha256);
                continue;
            }

            $includes[str_replace('%package%', $name, $providerFormat)] = ['sha256' => $sha256];
        }

        ksort($includes);

        $content = json_encode([
            'packages' => [],
            'providers-url' => '/' . $providerFormat,
            'provider-includes' => $includes,
        ]);
        $this->storage->saveRoot($content);
    }

    /**
     * @param string $name  Provider group name, e.g. 'providers-2021-03', 'providers-this-week'.
     * @return bool Whether group name should now be retired.
     */
    private function providerIsOutdated(string $name): bool
    {
        $packageCount = $this->entityManager->getRepository(Package::class)
            ->getCountByGroup(str_replace('providers-', '', $name));

        return $packageCount === 0;
    }
}
