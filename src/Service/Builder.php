<?php

namespace Outlandish\Wpackagist\Service;

use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Storage\PackageStore;

class Builder
{
    /** @var PackageStore */
    private $storage;

    public function __construct(PackageStore $storage)
    {
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
        $providerDataJson = json_encode(['providers' => $providerJson]);
        $providersSha256 = hash('sha256', $providerDataJson);
        $this->storage->saveProvider("providers-$providerGroupName", $providersSha256, $providerDataJson);
    }

    public function updateRoot()
    {
        $providers = $this->storage->loadAllProviders();
        $content = json_encode([
            'packages' => [],
            'providers-url' => '/p/%package%$%hash%.json',
            'provider-includes' => $providers,
        ]);
        $this->storage->saveRoot($content);
    }
}
