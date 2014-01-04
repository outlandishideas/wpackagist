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
        return "http://wordpress.org/extend/plugins/" . $this->getName() . '/';
    }

    public function getDownloadUrl($version)
    {
        $filename = $version == 'trunk' ? $this->getName() : $this->getName() . '.' . $version;

        return "http://downloads.wordpress.org/plugin/$filename.zip";
    }

    public function getComposerProviderGroup()
    {
        return 'plugins-' . $this->getLastCommited()->format('Y');
    }

    /**
     * {@inheritdoc}
     * Adds legacy prefix
     */
    public function getPackages(&$uid = 1)
    {
        $packages = parent::getPackages($uid);
        $name = 'wpackagist/' . $this->getName();

        foreach ($this->versions as $version) {
            $json                      = $this->getPackageVersion($version, $uid); // we call again so we get different uids
            $json['name']              = $name;
            $packages[$name][$version] = $json;
        }

        return $packages;
    }
}