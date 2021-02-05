<?php

namespace Outlandish\Wpackagist\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        // Let Symfony's default error tracing happen in dev.
        if ($_SERVER['APP_ENV'] === 'dev') {
            return;
        }

        $exception = $event->getThrowable();
        $response = new Response();

        $is404 = false;
        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());

            if ($exception->getStatusCode() === 404) {
                $is404 = true;
            }
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->logger->log(
            $is404 ? LogLevel::INFO : LogLevel::CRITICAL,
            sprintf('%s – %s', get_class($exception), $exception->getMessage())
        );

        $response->setContent($is404 ? 'The requested page could not be found.' : 'Something went wrong.');

        $event->setResponse($response);
    }
}
