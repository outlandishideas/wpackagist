<?php

namespace Outlandish\Wpackagist\Storage;

use Symfony\Component\Finder\Finder;

/**
 * Could support local use when no need to scale horizontally; also used briefly with
 * EFS in late 2020. This was very slow! Quicker key/value options should be preferred
 * except for local testing.
 */
final class Filesystem extends PackageStore
{
    protected $basePath;
    protected $isPartial = false;
    protected $packageDir = 'p';

    protected $packages = [];

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function prepare($partial = false): void
    {
        $this->isPartial = $partial;

        if (!$partial) {
            $this->packageDir = 'p.new' . date('YmdHis');
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mkdir("{$this->basePath}/{$this->packageDir}/wpackagist-plugin");
            $fs->mkdir("{$this->basePath}/{$this->packageDir}/wpackagist-theme");
        }
    }

    protected function readFile($path)
    {
        $contents = null;
        if (file_exists($path)) {
            $contents = file_get_contents($path);
        }
        return $contents;
    }

    protected function writeFile($path, $json)
    {
        return file_put_contents($path, $json) > 0;
    }

    protected function getResourcePath($packageName, $hash): string
    {
        return "{$this->basePath}/{$this->packageDir}/{$packageName}\${$hash}.json";
    }

    public function loadPackage(string $packageName, string $hash): ?string
    {
        return $this->readFile($this->getResourcePath($packageName, $hash));
    }

    public function savePackage(string $packageName, string $hash, string $json): bool
    {
        $this->packages[$packageName] = $json;
        return $this->writeFile($this->getResourcePath($packageName, $hash), $json);
    }

    public function loadAllPackages($packageNames)
    {
        $json = [];
        $toFind = [];
        foreach ($packageNames as $name) {
            if (array_key_exists($name, $this->packages)) {
                $json[$name] = $this->packages[$name];
            } else {
                $toFind[] = $name;
            }
        }

        if ($toFind) {
            $finder = new Finder();
            $finder->files()->in("{$this->basePath}/{$this->packageDir}")->depth('> 0');
            foreach ($finder as $file) {
                if (preg_match('/^(.+)\$[0-9a-f]{64}\.json$/', $file->getRelativePathname(), $matches)) {
                    $packageName = str_replace('\\', '/', $matches[1]);
                    if (in_array($packageName, $toFind)) {
                        $json[$packageName] = $file->getContents();
                    }
                }
            }
        }
        return $json;
    }

    public function loadAllProviders()
    {
        $finder = new Finder();
        $finder->files()->in("{$this->basePath}/{$this->packageDir}")->depth(0);
        $providers = [];
        foreach ($finder as $file) {
            if (preg_match('/^(.+)\$[0-9a-f]{64}\.json$/', $file->getRelativePathname(), $matches)) {
                $packageName = str_replace('\\', '/', $matches[1]);
                $providers[$packageName] = $file->getContents();
            }
        }
        return $providers;
    }

    public function loadProvider(string $group, string $hash): ?string
    {
        return $this->readFile($this->getResourcePath($group, $hash));
    }

    public function saveProvider(string $name, string $hash, string $json): bool
    {
        return $this->writeFile($this->getResourcePath($name, $hash), $json);
    }

    public function loadRoot(): ?string
    {
        return $this->readFile("{$this->basePath}/packages.json");
    }

    public function saveRoot(string $json): bool
    {
        return $this->writeFile("{$this->basePath}/packages.json", $json);
    }

    public function persist($final = false): void
    {
        if ($final && !$this->isPartial) {
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->rename("{$this->basePath}/p", "{$this->basePath}/p.old");
            $fs->rename("{$this->basePath}/{$this->packageDir}", "{$this->basePath}/p");
            $fs->remove("{$this->basePath}/p.old");
            $this->packageDir = 'p';
        }
    }


}
