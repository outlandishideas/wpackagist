<?php

namespace Outlandish\Wpackagist\Package;

class Core extends AbstractPackage
{
    protected $name = 'wordpress';

    public function getVendorName()
    {
        return 'wpackagist';
    }

    public function getComposerType()
    {
        return 'library';
    }

    public static function getSvnBaseUrl()
    {
        return 'http://core.svn.wordpress.org/';
    }

    public function getSvnUrl()
    {
        return static::getSvnBaseUrl();
    }

    public function getHomepageUrl()
    {
        return "http://wordpress.org/";
    }

    public function getDownloadUrl($version)
    {
        if ($this->versions[$version] == 'trunk') {
            return 'https://github.com/WordPress/WordPress/archive/master.tar.gz';
        } else {
            return 'http://wordpress.org/wordpress-' . $version . '.tar.gz';
        }
    }

    public function getPackageVersion($version, &$uid = 1)
    {
        $package = parent::getPackageVersion($version, $uid);

        $package['dist']['type'] = 'tar';

        return $package;
    }
}
