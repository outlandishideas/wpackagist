<?php

namespace Outlandish\Wpackagist\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private LoggerInterface $logger;

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

        $message = 'Something went wrong.';
        $exception = $event->getThrowable();
        $response = new Response();

        $this->logger->critical(sprintf('%s – %s', get_class($exception), $exception->getMessage()));

        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());

            if ($exception->getStatusCode() === 404) {
                $message = 'The requested page could not be found.';
            }
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response->setContent($message);

        $event->setResponse($response);
    }
}
