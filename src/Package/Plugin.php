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
        $isTrunk = $this->versions[$version] === 'trunk';

        //Assemble file name and append ?timestamp= variable to the trunk version to avoid Composer cache when plugin/theme author only updates the trunk
        $filename = ($isTrunk ? $this->getName() : $this->getName().'.'.$version) . '.zip' . ($isTrunk ? '?timestamp=' . urlencode($this->last_committed->format('U')) : '');

        return "https://downloads.wordpress.org/plugin/{$filename}";
    }
}
