<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class PackageData
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=200)
     */
    protected string $key;

    /**
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    protected string $value;

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
