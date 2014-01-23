<?php

namespace Outlandish\Wpackagist\Package;

use Composer\Package\Version\VersionParser;

abstract class AbstractPackage
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var DateTime
     */
    protected $last_committed;

    /**
     * @var DateTime
     */
    protected $last_fetched;

    /**
     * @var array
     */
    protected $versions = array();

    public function __construct(array $data = array())
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        if (is_string($this->versions)) {
            $this->versions = json_decode($this->versions, true);
        }
        if (!$this->versions) {
            $this->versions = array();
        }

        if (is_string($this->last_committed)) {
            $this->last_committed = new \DateTime($this->last_committed);
        }

        if (is_string($this->last_fetched)) {
            $this->last_fetched = new \DateTime($this->last_fetched);
        }
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string package shortname
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Ex: wpackagist
     * @return string
     */
    abstract public function getVendorName();

    /**
     * Ex: http://plugins.svn.wordpress.org/
     * @return string
     */
    public static function getSvnBaseUrl()
    {
        throw new \BadMethodCallException("Not implemented");
    }

    public function getSvnUrl()
    {
        return static::getSvnBaseUrl() . $this->getName() . '/';
    }

    /**
     * Ex: wordpress-plugin
     * @return string|null
     */
    public function getComposerType()
    {
        return null;
    }

    /**
     * Ex: themes-2014
     * @return string
     */
    abstract public function getComposerProviderGroup();

    /**
     * @return string "wpackagist-TYPE/PACKAGE"
     */
    public function getPackageName()
    {
        return $this->getVendorName() . '/' . $this->getName();
    }

    /**
     * Ex: http://wordpress.org/extend/themes/THEME/
     * @return string URL
     */
    abstract public function getHomepageUrl();

    /**
     * Ex: http://downloads.wordpress.org/plugin/plugin.1.0.zip
     * @return string URL
     */
    abstract public function getDownloadUrl($version);

    /**
     * @return DateTime|null
     */
    public function getLastCommited()
    {
        return $this->last_committed;
    }

    /**
     * @return DateTime|null
     */
    public function getLastFetched()
    {
        return $this->last_fetched;
    }

    /**
     * @return array
     */
    public function getVersions()
    {
        return $this->versions;
    }

    public function getPackages(&$uid = 1)
    {
        $packages = array();

        foreach ($this->versions as $version => $tag) {
            $packages[$this->getPackageName()][$version] = $this->getPackageVersion($version, $uid);
        }

        return $packages;
    }

    public function getPackageVersion($version, &$uid = 1)
    {
        $versionParser = new VersionParser;
        try {
            $normalizedVersion = $versionParser->normalize($version);
        } catch (\UnexpectedValueException $e) {
            return false; //skip packages with weird version numbers
        }

        $tag = $this->versions[$version];

        $package = array(
            'name'               => $this->getPackageName(),
            'version'            => $version,
            'version_normalized' => $normalizedVersion,
            'uid'                => $uid++,
        );

        if ($version == 'dev-trunk') {
            $package['time'] = $this->getLastCommited()->format('Y-m-d H:i:s');
        }

        if ($url = $this->getDownloadUrl($version)) {
            $package['dist'] = array(
                'type' => 'zip',
                'url'  => $url,
            );
        }

        if (($url = $this->getSvnUrl()) && $tag) {
            $package['source'] = array(
                'type'      => 'svn',
                'url'       => $this->getSvnUrl(),
                'reference' => $tag,
            );
        }

        if ($url = $this->getHomepageUrl()) {
            $package['homepage'] = $url;
        }

        if ($type = $this->getComposerType()) {
            $package['require']['composer/installers'] = '~1.0';
            $package['type'] = $type;
        }

        return $package;
    }
}