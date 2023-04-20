<?php

declare(strict_types=1);

namespace Outlandish\Wpackagist\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Psr\Log\LoggerInterface;

/**
 * An Entity Manager intended to recover gracefully from closed connection type errors, by swapping in a
 * fresh underlying EM if necessary to complete an operation.
 *
 * Adapted from @link https://medium.com/lebouchondigital/thread-safe-business-logic-with-doctrine-f09c633f6554
 * and Mike Litoris's comment on the same.
 *
 * Adapted for Wpackagist from Big Give's MatchBot code Jan '23.
 * @link https://github.com/thebiggive/matchbot/blob/develop/src/Application/Persistence/RetrySafeEntityManager.php
 */
class RetrySafeEntityManager extends EntityManagerDecorator
{
    private EntityManagerInterface $entityManager;

    /**
     * @var int For non-matching updates that always use Doctrine, maximum number of times to try again when
     *          Doctrine reports that the error is recoverable and that retrying makes sense
     */
    private int $maxLockRetries = 3;

    private Connection $connection;

    private LoggerInterface $logger;

    private ORM\Configuration $ormConfig;

    public function __construct(
        Connection $connection,
        ORM\Configuration $ormConfig,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->ormConfig = $ormConfig;

        $this->entityManager = $this->buildEntityManager();
        parent::__construct($this->entityManager);
    }

    public function transactional($callback)
    {
        $retries = 0;
        do {
            $this->beginTransaction();

            try {
                $ret = $callback();

                $this->flush();
                $this->commit();

                return $ret;
            } catch (RetryableException $ex) {
                $this->rollback();
                $this->close();

                $this->logger->warning('RetrySafeEntityManager rolling back from ' . get_class($ex));
                usleep(random_int(0, 200000)); // Wait between 0 and 0.2 seconds before retrying

                $this->resetManager();
                ++$retries;
            } catch (\Exception $ex) {
                $this->rollback();
                $this->logger->error(
                    'RetrySafeEntityManager bailing out having hit ' . get_class($ex) . ': ' . $ex->getMessage()
                );

                throw $ex;
            }
        } while ($retries < $this->maxLockRetries);

        $this->logger->error('RetrySafeEntityManager bailing out after ' . $this->maxLockRetries . ' tries');

        throw $ex;
    }

    /**
     * Attempt a persist the normal way, and if the underlying EM is closed, make a new one
     * and try a second time. We were forced to take this approach because the properties
     * tracking a closed EM are annotated private.
     *
     * {@inheritDoc}
     */
    public function persist($object): void
    {
        try {
            $this->entityManager->persist($object);
        } catch (EntityManagerClosed $closedException) {
            $this->logger->warning('EM closed. RetrySafeEntityManager::persist() trying with a new instance');
            $this->resetManager();
            $this->entityManager->persist($object);
        }
    }

    /**
     * Attempt a flush the normal way, and if the underlying EM is closed, make a new one
     * and try a second time. We were forced to take this approach because the properties
     * tracking a closed EM are annotated private.
     *
     * {@inheritDoc}
     */
    public function flush($entity = null): void
    {
        try {
            $this->entityManager->flush($entity);
        } catch (EntityManagerClosed $closedException) {
            $this->logger->warning('EM closed. RetrySafeEntityManager::flush() trying with a new instance');
            $this->resetManager();
            $this->entityManager->flush($entity);
        }
    }

    public function resetManager(): void
    {
        $this->entityManager = $this->buildEntityManager();
    }

    /**
     * We need to override the base `EntityManager` call with the equivalent so that repositories
     * contain the retry-safe EM (i.e. `$this` in our current context) and not the default one.
     */
    public function getRepository($className)
    {
        return $this->ormConfig->getRepositoryFactory()->getRepository($this, $className);
    }

    private function buildEntityManager(): EntityManagerInterface
    {
        return EntityManager::create($this->connection, $this->ormConfig);
    }
}
