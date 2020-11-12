<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Plugin extends Package
{
    const VENDOR_NAME = 'wpackagist-plugin';
    const COMPOSER_TYPE = 'wordpress-plugin';
    const SVN_BASE_URL = 'https://plugins.svn.wordpress.org/';
    const HOMEPAGE_BASE_URL = 'https://wordpress.org/plugins/';

    public function getDownloadUrl($version): string
    {
        $isTrunk = $this->versions[$version] === 'trunk';

        //Assemble file name and append ?timestamp= variable to the trunk version to avoid Composer cache when plugin/theme author only updates the trunk
        $filename = ($isTrunk ? $this->getName() : $this->getName().'.'.$version) . '.zip' . ($isTrunk ? '?timestamp=' . urlencode($this->lastCommitted->format('U')) : '');

        return "https://downloads.wordpress.org/plugin/{$filename}";
    }
}
