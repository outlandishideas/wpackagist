<?php

namespace Outlandish\Wpackagist\Storage;

abstract class PackageStore
{
    /**
     * @param string $packageName
     * @param string $hash
     * @return string|null  Blank if not found.
     */
    abstract public function loadPackage(string $packageName, string $hash): ?string;

    /**
     * @param string $name
     * @param string $hash
     * @return string|null  Blank if not found.
     */
    abstract public function loadProvider(string $name, string $hash): ?string;

    /**
     * @return string|null  Blank if not found.
     */
    abstract public function loadRoot(): ?string;

    /**
     * @param string[] $packageNames
     * @return string[]
     */
    abstract public function loadAllPackages(array $packageNames);

    /**
     * @return string[]
     */
    abstract public function loadAllProviders();

    /**
     * @param string $packageName
     * @param string $hash
     * @param string $json
     * @return bool Success or failure.
     */
    abstract public function savePackage(string $packageName, string $hash, string $json): bool;

    /**
     * @param string $name
     * @param string $hash
     * @param string $json
     * @return bool Success or failure.
     */
    abstract public function saveProvider(string $name, string $hash, string $json): bool;

    /**
     * @param string $json
     * @return bool Success or failure.
     */
    abstract public function saveRoot(string $json): bool;

    /**
     * Override with any once-per-run setup the store requires, if applicable.
     * @param bool $partial
     */
    public function prepare($partial = false): void
    {
    }

    /**
     * Override with any persistence steps for the process.
     * @param bool $final True indicates the last persistence step, and the process is complete
     */
    public function persist($final = false): void
    {
    }
}
