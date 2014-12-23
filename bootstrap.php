<?php

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    include __DIR__.'/vendor/autoload.php';
} else {
    die('You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}

return new Outlandish\Wpackagist\Application();
