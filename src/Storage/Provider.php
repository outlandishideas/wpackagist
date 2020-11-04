<?php

namespace Outlandish\Wpackagist\Storage;

abstract class Provider
{
    /**
     * @param string $key
     * @return string|null  Blank if not found.
     */
    abstract public function load(string $key): ?string;

    /**
     * @param string $key
     * @param string $json
     * @return bool Success or failure.
     */
    abstract public function save(string $key, string $json): bool;

    /**
     * Override with any once-per-run setup the Provider requires, if applicable.
     */
    public function prepare(): void
    {
    }

    /**
     * Override with any once-per-run persistence steps for the end of the process.
     */
    public function finalise(): void
    {
    }
}
