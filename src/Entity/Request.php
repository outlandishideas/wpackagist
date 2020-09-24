<?php

namespace Outlandish\Wpackagist\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="requests")
 */
class Request
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string", length=15, unique=true)
     */
    protected string $ipAddress;

    /**
     * @ORM\Column(type="datetime")
     */
    protected DateTime $lastRequest;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $requestCount;
}
