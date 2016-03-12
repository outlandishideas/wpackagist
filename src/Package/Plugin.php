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
        return 'https://plugins.svn.wordpress.org/';
    }

    public function getHomepageUrl()
    {
        return "https://wordpress.org/plugins/".$this->getName().'/';
    }

    public function getDownloadUrl($version)
    {
        $filename = $this->versions[$version] == 'trunk' ? $this->getName() : $this->getName().'.'.$version;

        return "https://downloads.wordpress.org/plugin/$filename.zip";
    }
}
