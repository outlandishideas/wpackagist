<?php

namespace Outlandish\Wpackagist;

use Silex\Application as SilexApplication;

class Application extends SilexApplication
{
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $this->registerDoctrine();
        $this->registerConsole();
    }

    protected function registerDoctrine()
    {
        $this->register(new Service\DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_sqlite',
                'path'   => dirname(__DIR__).'/data/packages.sqlite',
            ),
        ));
    }

    protected function registerConsole()
    {
        $this->register(new Service\ConsoleServiceProvider(), array(
            'console.name'              => 'Wpackagist',
            'console.version'           => '1.0.0',
            'console.project_directory' => dirname(__DIR__),
        ));
    }
}
