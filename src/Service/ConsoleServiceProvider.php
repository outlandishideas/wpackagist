<?php

namespace Outlandish\Wpackagist\Service;

use Knp\Provider\ConsoleServiceProvider as BaseConsoleServiceProvider;
use Outlandish\Wpackagist\Command;
use Silex\Application;

class ConsoleServiceProvider extends BaseConsoleServiceProvider
{
    public function register(Application $app)
    {
        parent::register($app);

        $app['console'] = $app->share($app->extend('console', function ($console, $app) {
            $console->add(new Command\RefreshCommand());
            $console->add(new Command\UpdateCommand());
            $console->add(new Command\BuildCommand());

            return $console;
        }));
    }
}
