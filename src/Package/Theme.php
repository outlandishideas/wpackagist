<?php

namespace Outlandish\Wpackagist\Package;

class Theme extends AbstractPackage
{
    public function getVendorName()
    {
        return 'wpackagist-theme';
    }

    public function getComposerType()
    {
        return 'wordpress-theme';
    }

    public static function getSvnBaseUrl()
    {
        return 'http://themes.svn.wordpress.org/';
    }

    public function getHomepageUrl()
    {
        return "https://wordpress.org/themes/".$this->getName().'/';
    }

    public function getDownloadUrl($version)
    {
        return "https://downloads.wordpress.org/theme/".$this->getName().".$version.zip";
    }
}
