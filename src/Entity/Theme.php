<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Theme extends Package
{
    const VENDOR_NAME = 'wpackagist-theme';
    const COMPOSER_TYPE = 'wordpress-theme';
    const SVN_BASE_URL = 'https://themes.svn.wordpress.org/';
    const HOMEPAGE_BASE_URL = 'https://wordpress.org/themes/';

    public function getDownloadUrl($version): string
    {
        return "https://downloads.wordpress.org/theme/".$this->getName().".$version.zip";
    }
}
