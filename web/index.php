<?php

use Outlandish\Wpackagist\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_FORWARDED);
} else {
    /**
     * If no env override, get the correct request context behind an AWS load balancer.
     * @link https://symfony.com/doc/5.2/deployment/proxies.html
     */
    Request::setTrustedProxies(
        ['127.0.0.1', 'REMOTE_ADDR'], // REMOTE_ADDR string replaced at runtime.
        Request::HEADER_X_FORWARDED_AWS_ELB
    );
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
