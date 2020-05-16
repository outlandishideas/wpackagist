<?php

$app = require_once dirname(__DIR__).'/bootstrap.php';

use Symfony\Component\HttpFoundation\Response;

$app->error(function (\Exception $e, $code) use ($app) {

    switch ($code) {
        case 404:
            return new Response('The requested page could not be found.', 404);
        default:
            return new Response('Something went wrong.', 500);
    }

});

$app->run();
