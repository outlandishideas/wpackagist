<?php

namespace Outlandish\Wpackagist;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    protected $db = null;

    public function __construct()
    {
        parent::__construct('Wpackagist');
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $this->add(new RefreshCommand());
        $this->add(new UpdateCommand());
        $this->add(new BuildCommand());
    }

    public function getDb()
    {
        if (null === $this->db) {
            $provider = new DatabaseProvider;
            $this->db = $provider->getDb();
        }

        return $this->db;
    }
}
