<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * This repo uses a custom constructor so it can directly log record contention edge cases. For this reason it is
 * declared as a service in `services.yaml` and you cannot use `EntityManagerInterface::getRepository()` to retrieve
 * it. You should instead inject it directly with DI instead.
 */
class RequestRepository extends EntityRepository
{
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(EntityManagerInterface $em, Mapping\ClassMetadata $class, LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct($em, $class);
    }

    /**
     * Get a count of sensitive requests for an IP, incrementing the counter as a side effect.
     *
     * @param string    $ip
     * @param int       $previousTries  Work around record contention edge case while avoiding risk of an infinite loop.
     * @return int The number of requests within the past 24 hours
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getRequestCountByIp(string $ip, int $previousTries): int
    {
        $em = $this->getEntityManager();
        $qb = new QueryBuilder($em);

        $oneHourAgo = (new \DateTime())->sub(new \DateInterval('PT1H'));

        $qb->select('r')
            ->from(Request::class, 'r')
            ->where('r.ipAddress = :ip')
            ->andWhere('r.lastRequest >= :cutoff')
            ->setParameter('ip', $ip)
            ->setParameter('cutoff', $oneHourAgo);

        // `wrapInTransaction()` auto-flushes on commit / return, but we still saw a rare edge case where an insert
        // led to a unique IP constraint violation. For now we are logging a warning when this happens and trying
        // a second time.
        // See https://doctrine2.readthedocs.io/en/latest/reference/transactions-and-concurrency.html#approach-2-explicitly
        $requestItem = $em->wrapInTransaction(function () use ($em, $qb, $ip, $previousTries, $oneHourAgo) {
            $requestHistory = $qb->getQuery()->getResult();

            if (empty($requestHistory)) {
                $this->deleteOldRequestCounts($ip, $oneHourAgo);

                $requestItem = new Request();
                $requestItem->setIpAddress($ip);
            } else {
                $requestItem = $requestHistory[0];
            }

            $requestItem->addRequest();
            $em->persist($requestItem);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException $exception) {
                $logLevel = $previousTries === 0 ? LogLevel::WARNING : LogLevel::ERROR;
                $this->logger->log(
                    $logLevel,
                    sprintf(
                        'UniqueConstraintViolationException led to access insert retry for IP %s',
                        $ip
                    )
                );

                if ($previousTries > 0) {
                    // "Fail safe" by simulating a very high request count if something is going persistently wrong.
                    $dummyRequest = new Request();
                    $dummyRequest->setRequestCount(100000);
                    return $dummyRequest;
                }

                // If we hit a locking edge case just once, try a 2nd time.
                return $this->getRequestCountByIp($ip, $previousTries + 1);
            }

            return $requestItem;
        });

        return $requestItem->getRequestCount();
    }

    /**
     * Remove expired entries for the provided IP address.
     *
     * @param string $ip
     * @param \DateTime $cutoff
     */
    private function deleteOldRequestCounts(string $ip, \DateTime $cutoff): void
    {
        $em = $this->getEntityManager();
        $qb = new QueryBuilder($em);
        $qb->delete(Request::class, 'r')
            ->where('r.ipAddress = :ip')
            ->andWhere('r.lastRequest < :cutoff')
            ->setParameter('ip', $ip)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        // Ensure the old record is deleted at DB level before the insert coming up, so we don't
        // fall foul of the IP uniqueness constraint.
        $em->flush();
    }
}
