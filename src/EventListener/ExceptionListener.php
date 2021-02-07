<?php

namespace Outlandish\Wpackagist\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Reduce log severity for scan junk & actual not found requests, inc. unexpected methods like
        // `POST /` and made up HTTP methods, e.g. `Invalid method override "__CONSTRUCT"`.
        $notFound = (
            $exception instanceof BadRequestHttpException ||
            $exception instanceof MethodNotAllowedHttpException ||
            $exception instanceof NotFoundHttpException
        );

        $this->logger->log(
            $notFound ? LogLevel::INFO : LogLevel::CRITICAL,
            sprintf('%s – %s', get_class($exception), $exception->getMessage())
        );

        $response->setContent($notFound ? 'The requested page could not be found.' : 'Something went wrong.');

        $event->setResponse($response);
    }
}
