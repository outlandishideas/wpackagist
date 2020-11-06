<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(indexes={@ORM\Index(name="package_data_is_latest_idx", columns={"is_latest"})})
 */
class PackageData
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=10)
     * @var string
     */
    protected $type;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=200)
     * @var string
     */
    protected $name;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=64)
     * @var string
     */
    protected $hash;

    /**
     * @ORM\Column(type="text", length=65535, nullable=true)
     * @var string
     */
    protected $value;

    /**
     * @ORM\Column(type="boolean", options={"default": 0}, nullable=false)
     * @var boolean
     */
    protected $isLatest = false;

    /**
     * @return string
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return bool
     */
    public function getIsLatest(): bool
    {
        return $this->isLatest;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function setIsLatest(bool $latest): void
    {
        $this->isLatest = $latest;
    }
}
