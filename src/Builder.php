<?php

namespace Outlandish\Wpackagist;

use Outlandish\Wpackagist\Entity\Package;

class Builder
{
    private Storage\Provider $storage;

    public function __construct(Storage\Provider $storage)
    {
        $this->storage = $storage;
    }

    public function updatePackage(Package $package): void
    {
        $versionHashArrays = [];

        $packagesData = $package->getPackages($uid);

        foreach ($packagesData as $packageName => $packageData) {
            $content = json_encode(['packages' => [$packageName => $packageData]]);
            $packageSha256 = hash('sha256', $content);
            $versionHashArrays[$this->getComposerProviderGroup($package)][$packageName] = [
                'sha256' => $packageSha256,
            ];
            $this->storage->save("p/$packageName\$$packageSha256.json", $content);
        }

        $this->updateProviderGroup(
            $this->getComposerProviderGroup($package),
            $packageName,
            $versionHashArrays,
        );
    }

    /**
     * @param string $providerGroupName
     * @param string $packageName
     * @param array $packageSha256es    Array of SHA-256 hash arrays, keyed on version.
     */
    protected function updateProviderGroup(string $providerGroupName, string $packageName, array $packageSha256es): void
    {
        $providerInnerData = [$packageName => ['sha256' => $packageSha256es]];
        $providerDataJson = json_encode(['providers' => $providerInnerData]);
        $providersSha256 = hash('sha256', $providerDataJson);
        $providerIncludes["p/providers-$providerGroupName\$%hash%.json"] = [
            'sha256' => $providersSha256,
        ];
        $this->storage->save("p/providers-$providerGroupName\$$providersSha256.json", $providerDataJson);

        $this->updateRoot($providerIncludes, false);
    }

    /**
     * Return a string to split packages in more-or-less even groups
     * of their last modification. Minimizes groups modifications.
     *
     * @return string
     */
    public function getComposerProviderGroup(Package $package)
    {
        $date = $package->getLastCommitted();

        if ($date === null) {
            return 'old';
        }

        if ($date >= new \DateTime('monday last week')) {
            return 'this-week';
        }

        if ($date >= new \DateTime(date('Y') . '-01-01')) {
            // split current by chunks of 3 months, current month included
            // past chunks will never be update this year
            $month = $date->format('n');
            $month = ceil($month / 3) * 3;
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);

            return $date->format('Y-') . $month;
        }

        if ($date >= new \DateTime('2011-01-01')) {
            // split by years, limit at 2011 so we never update 'old' again
            return $date->format('Y');
        }

        // 2010 and older is about 2M, which is manageable
        // Still some packages ? Probably junk/errors
        return 'old';
    }

    public function updateRoot(array $providerIncludes, bool $complete)
    {
        if ($complete) {
            $content = json_encode([
                'packages' => [],
                'providers-url' => '/p/%package%$%hash%.json',
                'provider-includes' => $providerIncludes,
            ]);
        } else {
            $contentDecoded = json_decode($this->storage->load('packages.json'), true);
            foreach ($providerIncludes as $providerKey => $providerInclude) {
                $contentDecoded['provider-includes'][$providerKey] = $providerInclude;
            }
            $content = json_encode($contentDecoded);
        }

        $this->storage->save('packages.json', $content);
    }
}
