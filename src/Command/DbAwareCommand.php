<?php

namespace Outlandish\Wpackagist\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;

abstract class DbAwareCommand extends Command
{
    /** @var Connection */
    protected $connection;

    public function __construct(Connection $connection, $name = null)
    {
        $this->connection = $connection;

        parent::__construct($name);
    }
}
