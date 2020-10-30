<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Plugin extends Package
{
    public function getVendorName(): string
    {
        return 'wpackagist-plugin';
    }

    public function getComposerType(): string
    {
        return 'wordpress-plugin';
    }

    public static function getSvnBaseUrl(): string
    {
        return 'https://plugins.svn.wordpress.org/';
    }

    public function getHomepageUrl(): string
    {
        return "https://wordpress.org/plugins/".$this->getName().'/';
    }

    public function getDownloadUrl($version): string
    {
        $isTrunk = $this->versions[$version] === 'trunk';

        //Assemble file name and append ?timestamp= variable to the trunk version to avoid Composer cache when plugin/theme author only updates the trunk
        $filename = ($isTrunk ? $this->getName() : $this->getName().'.'.$version) . '.zip' . ($isTrunk ? '?timestamp=' . urlencode($this->lastCommitted->format('U')) : '');

        return "https://downloads.wordpress.org/plugin/{$filename}";
    }
}
