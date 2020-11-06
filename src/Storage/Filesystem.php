<?php

namespace Outlandish\Wpackagist\Storage;

/**
 * Could support local use when no need to scale horizontally; also used briefly with
 * EFS in late 2020. This was very slow! Quicker key/value options should be preferred
 * except for local testing.
 */
final class Filesystem extends PackageStore
{
    public function prepare($partial = false): void
    {
        //todo: when partial = false, create a parallel directory, which is renamed in persist()
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->mkdir("{$this->getBasePath()}/p/wpackagist-plugin");
        $fs->mkdir("{$this->getBasePath()}/p/wpackagist-theme");
    }

    public function loadPackage(string $packageName, string $hash): ?string
    {
        return file_get_contents($this->getResourcePath($packageName, $hash)) ?: null;
    }

    public function savePackage(string $packageName, string $hash, string $json): bool
    {
        return file_put_contents($this->getResourcePath($packageName, $hash), $json) > 0;
    }

    protected function getResourcePath($packageName, $hash): string
    {
        return "{$this->getBasePath()}/{$packageName}\${$hash}.json";
    }

    protected function getBasePath(): string
    {
        return $_SERVER['PACKAGE_PATH'];
    }

    public function loadAllPackages($packageNames)
    {
        // TODO: Implement loadAll() method.
    }

    public function loadAllProviders()
    {
        // TODO: Implement loadAll() method.
    }

    public function loadProvider(string $group, string $hash): ?string
    {
        // TODO: Implement loadProvider() method.
    }

    public function loadRoot(): ?string
    {
        // TODO: Implement loadRoot() method.
    }

    public function saveProvider(string $name, string $hash, string $json): bool
    {
        // TODO: Implement saveProvider() method.
    }

    public function saveRoot(string $json): bool
    {
        // TODO: Implement saveRoot() method.
    }
}
