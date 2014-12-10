<?php

namespace Outlandish\Wpackagist\Package;

class Plugin extends AbstractPackage
{
    public function getVendorName()
    {
        return 'wpackagist-plugin';
    }

    public function getComposerType()
    {
        return 'wordpress-plugin';
    }

    public static function getSvnBaseUrl()
    {
        return 'http://plugins.svn.wordpress.org/';
    }

    public function getHomepageUrl()
    {
        return "http://wordpress.org/plugins/" . $this->getName() . '/';
    }

    public function getDownloadUrl($version)
    {
        $filename = $this->versions[$version] == 'trunk' ? $this->getName() : $this->getName() . '.' . $version;

        return "http://downloads.wordpress.org/plugin/$filename.zip";
    }

    /**
     * {@inheritdoc}
     * Adds legacy prefix
     */
    public function getPackages(&$uid = 1)
    {
        $packages = parent::getPackages($uid);
        $name = 'wpackagist/' . $this->getName();

        foreach ($this->versions as $version => $tag) {
            try {
                $json                      = $this->getPackageVersion($version, $uid); // we call again so we get different uids
                $json['name']              = $name;
                $packages[$name][$version] = $json;
                $packages[$this->getPackageName()][$version]['replace'][$name] = 'self.version';
            } catch (\UnexpectedValueException $e) {
                // skip packages with weird version numbers
            }
        }

        return $packages;
    }
}
