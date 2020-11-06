<?php

namespace Outlandish\Wpackagist\Entity;

use Composer\Package\Version\VersionParser;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PackageRepository::class)
 * @ORM\Table(
 *     name="packages",
 *     uniqueConstraints={
 *      @ORM\UniqueConstraint(name="type_and_name_unique", columns={"class_name", "name"}),
 *     },
 *     indexes={
 *      @ORM\Index(name="last_committed_idx", columns={"last_committed"}),
 *      @ORM\Index(name="last_fetched_idx", columns={"last_fetched"}),
 *      @ORM\Index(name="provider_group_idx", columns={"provider_group"}),
 *      @ORM\Index(name="package_is_active_idx", columns={"is_active"}),
 *     }
 * )
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="class_name", type="string")
 * @ORM\DiscriminatorMap({
 *     "Outlandish\Wpackagist\Entity\Plugin" = "Plugin",
 *     "Outlandish\Wpackagist\Entity\Theme" = "Theme",
 * })
 */
abstract class Package
{
    const VENDOR_NAME = '';
    const COMPOSER_TYPE = '';
    const SVN_BASE_URL = '';
    const HOMEPAGE_BASE_URL = '';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string WordPress package name
     */
    protected $name;

    /**
     * @ORM\Column(type="string", options={"default": "old"}, nullable=false)
     * @var string WordPress package name
     */
    protected $providerGroup;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $lastCommitted;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $lastFetched = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     * @var array|null
     */
    protected $versions = null;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $isActive;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null WordPress package name
     */
    protected $displayName = null;

    /**
     * @return string   URL, e.g. 'https://downloads.wordpress.org/plugin/plugin.1.0.zip'.
     */
    abstract public function getDownloadUrl($version): string;

    /**
     * @return string   e.g. 'wpackagist'.
     */
    public function getVendorName(): string
    {
        return static::VENDOR_NAME;
    }

    /**
     * @return string|null  e.g. 'wordpress-plugin'.
     */
    public function getComposerType(): string
    {
        return static::COMPOSER_TYPE;
    }

    /**
     * @return string   e.g. https://plugins.svn.wordpress.org/
     */
    public static function getSvnBaseUrl(): string
    {
        return static::SVN_BASE_URL;
    }

    /**
     * @return string   URL, e.g. 'https://wordpress.org/extend/themes/THEME/'.
     */
    public function getHomepageUrl(): ?string
    {
        return static::HOMEPAGE_BASE_URL . $this->getName() . '/';
    }

    /**
     * @return string package shortname
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getSvnUrl(): string
    {
        return static::getSvnBaseUrl() . "{$this->getName()}/";
    }

    /**
     * @return string "wpackagist-TYPE/PACKAGE"
     */
    public function getPackageName(): string
    {
        return $this->getVendorName() . '/' . $this->getName();
    }

    /**
     * @return DateTime
     */
    public function getLastCommitted(): DateTime
    {
        return $this->lastCommitted;
    }

    /**
     * @return DateTime|null
     */
    public function getLastFetched(): ?DateTime
    {
        return $this->lastFetched;
    }

    /**
     * @return array    3-dimensional associative array of Composer format data for every
     *                  package, ready to be JSON-encoded, where keys are:
     *                      * first level = package name;
     *                      * second level = version;
     *                      * third level = Composer metadata item, e.g. 'version_normalized'.
     */
    public function getVersionData(): array
    {
        if (empty($this->versions)) { // May be null when read from persisted data, or empty array
            return [];
        }

        $versions = [];
        foreach ($this->versions as $version => $tag) {
            try {
                $versions[$version] = $this->getPackageVersion($version);
            } catch (\UnexpectedValueException $e) {
                //skip packages with weird version numbers
            }
        }

        return $versions;
    }

    /**
     * @param $version
     * @return array    Associative array of Composer format data, ready to be JSON-encoded.
     * @throws \UnexpectedValueException
     */
    public function getPackageVersion($version)
    {
        $versionParser = new VersionParser();
        $normalizedVersion = $versionParser->normalize($version);

        $tag = $this->versions[$version];

        $package = [
            'name'               => $this->getPackageName(),
            'version'            => $version,
            'version_normalized' => $normalizedVersion,
            'uid'                => hash('sha256', $this->id . '|' . $normalizedVersion),
        ];

        if ($version === 'dev-trunk') {
            $package['time'] = $this->getLastCommitted()->format('Y-m-d H:i:s');
        }

        if ($url = $this->getDownloadUrl($version)) {
            $package['dist'] = [
                'type' => 'zip',
                'url'  => $url,
            ];
        }

        if (($url = $this->getSvnUrl()) && $tag) {
            $package['source'] = [
                'type'      => 'svn',
                'url'       => $this->getSvnUrl(),
                'reference' => $tag,
            ];
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

    public function getVersions(): ?array
    {
        return $this->versions;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $active)
    {
        $this->isActive = $active;
    }

    public function setVersions(array $versions)
    {
        $this->versions = $versions;
    }

    public function setDisplayName(string $displayName)
    {
        $this->displayName = $displayName;
    }

    public function setLastFetched(\DateTime $lastFetched)
    {
        $this->lastFetched = $lastFetched;
    }

    public function setLastCommitted(\DateTime $lastCommitted)
    {
        $this->lastCommitted = $lastCommitted;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string   Short type: 'plugin' or 'theme'.
     */
    public function getType(): string
    {
        return str_replace('wordpress-', '', $this->getComposerType());
    }

    public function getProviderGroup()
    {
        return $this->providerGroup;
    }

    public function generateProviderGroup()
    {
        $this->providerGroup = self::makeComposerProviderGroup($this->lastCommitted);
        return $this->providerGroup;
    }

    /**
     * Return a string to split packages in more-or-less even groups
     * of their last modification. Minimizes groups modifications.
     *
     * @param DateTime|string $date
     * @return string
     */
    public static function makeComposerProviderGroup($date)
    {
        if (empty($date)) {
            return 'old';
        }

        if (is_string($date)) {
            $date = new DateTime($date);
        }

        if ($date >= new DateTime('monday last week')) {
            return 'this-week';
        }

        if ($date >= new DateTime(date('Y') . '-01-01')) {
            // split current by chunks of 3 months, current month included
            // past chunks will never be update this year
            $month = $date->format('n');
            $month = ceil($month / 3) * 3;
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);

            return $date->format('Y-') . $month;
        }

        if ($date >= new DateTime('2011-01-01')) {
            // split by years, limit at 2011 so we never update 'old' again
            return $date->format('Y');
        }

        // 2010 and older is about 2M, which is manageable
        // Still some packages ? Probably junk/errors
        return 'old';
    }
}
