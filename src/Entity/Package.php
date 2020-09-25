<?php

namespace Outlandish\Wpackagist\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
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
 */
class Package
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected string $className;

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
    protected ?string $versions = null;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $isActive;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string WordPress package name
     */
    protected ?string $displayName = null;
}
