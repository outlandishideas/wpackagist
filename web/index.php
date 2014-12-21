<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

// Register twig templates path
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/templates',
));

// Routes
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig', array(
       'title' => "WordPress Packagist: Manage your plugins and themes with Composer",
    ));
});

$app->get('/search', function () use ($app) {
    return $app['twig']->render('search.twig', array(
       'title' => "WordPress Packagist: Search packages",
    ));
});

$app->run();
