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
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=15, unique=true)
     * @var string
     */
    protected $ipAddress;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $lastRequest;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $requestCount;
}
