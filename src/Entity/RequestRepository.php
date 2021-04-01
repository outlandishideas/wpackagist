<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class RequestRepository extends EntityRepository
{
    /**
     * Get a count of sensitive requests for an IP, incrementing the counter as a side effect.
     *
     * @param string $ip
     * @return int The number of requests within the past 24 hours
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getRequestCountByIp(string $ip): int
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

        // `transactional()` auto-flushes on commit / return, so this should prevent race
        // conditions where two threads are both trying to make a new record.
        // See https://doctrine2.readthedocs.io/en/latest/reference/transactions-and-concurrency.html#approach-2-explicitly
        $requestItem = $em->transactional(function () use ($em, $qb, $ip, $oneHourAgo) {
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
