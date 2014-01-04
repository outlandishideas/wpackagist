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
        return "http://wordpress.org/extend/themes/" . $this->getName() . '/';
    }

    /**
     * {@inheritdoc}
     *
     * Wordpress themes do not have trunk/branches/tags folders
     */
    public function getSvnRef($version)
    {
        return $version;
    }

    public function getDownloadUrl($version)
    {
        return "http://wordpress.org/themes/download/" . $this->getName() . ".$version.zip";
    }

    /**
     * {@inheritdoc}
     *
     * Wordpress themes do not have trunk/branches/tags folders
     */
    public function getSvnTagsUrl()
    {
        return $this->getSvnUrl();
    }

    public function getComposerProviderGroup()
    {
        return 'themes-' . $this->getLastCommited()->format('Y');
    }
}