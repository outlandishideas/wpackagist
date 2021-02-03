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
        ksort($providerJson);
        $providerDataJson = json_encode(['providers' => $providerJson]);
        $providersSha256 = hash('sha256', $providerDataJson);
        $this->storage->saveProvider("providers-$providerGroupName", $providersSha256, $providerDataJson);
    }

    public function updateRoot()
    {
        $providers = $this->storage->loadAllProviders();
        $includes = [];
        $providerFormat = 'p/%package%$%hash%.json';
        foreach ($providers as $name => $value) {
            // Skip/delete 3-monthly providers for any year that's not the current one,
            // allowing grouping to smoothly switch over at the start of a new year.
            // Packages themselves automatically update provider group e.g. from '2020-12'
            // to '2020' when the next year starts.
            if ($this->providerIsOutdated($name)) {
                continue;
            }

            $sha256 = hash('sha256', $value);
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
        $matchCount = preg_match('/^providers-(\\d{4})-\\d{2}$/', $name, $matches);
        if ($matchCount !== 1) {
            // Provider group formats other than YYYY-MM don't become outdated in this sense.
            return false;
        }

        return $matches[1] < date('Y');
    }
}
