<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table
 */
class State
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=50)
     */
    protected string $key;

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected string $value;
}
