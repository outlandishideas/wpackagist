<?php

namespace Outlandish\Wpackagist\Storage;

/**
 * Could support local use when no need to scale horizontally; also used briefly with
 * EFS in late 2020. This was very slow! Quicker key/value options should be preferred
 * except for local testing.
 */
final class Filesystem extends Provider
{
    public function prepare(): void
    {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->mkdir("{$this->getBasePath()}/p/wpackagist-plugin");
        $fs->mkdir("{$this->getBasePath()}/p/wpackagist-theme");
    }

    public function load(string $key): ?string
    {
        return file_get_contents("{$this->getBasePath()}/$key") ?: null;
    }

    public function save(string $key, string $json): bool
    {
        return file_put_contents("{$this->getBasePath()}/$key", $json) > 0;
    }

    protected function getBasePath(): string
    {
        return $_SERVER['PACKAGE_PATH'];
    }
}
