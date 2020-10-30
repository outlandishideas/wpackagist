<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Theme extends Package
{
    public function getVendorName(): string
    {
        return 'wpackagist-theme';
    }

    public function getComposerType(): string
    {
        return 'wordpress-theme';
    }

    public static function getSvnBaseUrl(): string
    {
        return 'https://themes.svn.wordpress.org/';
    }

    public function getHomepageUrl(): string
    {
        return "https://wordpress.org/themes/".$this->getName().'/';
    }

    public function getDownloadUrl($version): string
    {
        return "https://downloads.wordpress.org/theme/".$this->getName().".$version.zip";
    }
}
