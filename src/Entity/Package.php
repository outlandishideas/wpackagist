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
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string")
     * @var string WordPress package name
     */
    protected string $name;

    /**
     * @ORM\Column(type="datetime")
     */
    protected DateTime $lastCommitted;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTime $lastFetched = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected ?array $versions = null;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $isActive;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string WordPress package name
     */
    protected ?string $displayName = null;

    /**
     * @return string|null  e.g. 'wordpress-plugin'.
     */
    abstract public function getComposerType(): ?string;

    /**
     * @return string   URL, e.g. 'https://downloads.wordpress.org/plugin/plugin.1.0.zip'.
     */
    abstract public function getDownloadUrl($version): string;

    /**
     * @return string   URL, e.g. 'https://wordpress.org/extend/themes/THEME/'.
     */
    abstract public function getHomepageUrl(): ?string;

    /**
     * @return string   e.g. 'wpackagist'.
     */
    abstract public function getVendorName(): string;

    /**
     * @return string   e.g. https://plugins.svn.wordpress.org/
     */
    abstract public static function getSvnBaseUrl(): string;

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
     * @param int $uid
     * @return array    3-dimensional associative array of Composer format data for every
     *                  package, ready to be JSON-encoded, where keys are:
     *                      * first level = package name;
     *                      * second level = version;
     *                      * third level = Composer metadata item, e.g. 'version_normalized'.
     */
    public function getPackages(&$uid = 1): array
    {
        $packages = [];

        if (empty($this->versions)) { // May be null when read from persisted data, or empty array
            return $packages;
        }

        foreach ($this->versions as $version => $tag) {
            try {
                $packages[$this->getPackageName()][$version] = $this->getPackageVersion($version, $uid);
            } catch (\UnexpectedValueException $e) {
                //skip packages with weird version numbers
            }
        }

        return $packages;
    }

    /**
     * @param $version
     * @param  int                       $uid
     * @return array    Associative array of Composer format data, ready to be JSON-encoded.
     * @throws \UnexpectedValueException
     */
    public function getPackageVersion($version, &$uid = 1)
    {
        $versionParser = new VersionParser();
        $normalizedVersion = $versionParser->normalize($version);

        $tag = $this->versions[$version];

        $package = [
            'name'               => $this->getPackageName(),
            'version'            => $version,
            'version_normalized' => $normalizedVersion,
            'uid'                => $uid++,
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
}
