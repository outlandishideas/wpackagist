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
        $requestHistory = $qb->getQuery()->getResult();

        if (empty($requestHistory)) {
            $this->resetRequestCount($ip, $oneHourAgo);
            return 1;
        }

        /** @var Request $requestItem */
        $requestItem = $requestHistory[0];
        $requestItem->addRequest();
        $em->persist($requestItem);

        return $requestItem->getRequestCount();
    }

    /**
     * Add an entry to the requests table for the provided IP address.
     * Has the side effect of removing all expired entries.
     *
     * @param string $ip
     * @param \DateTime $cutoff
     */
    private function resetRequestCount(string $ip, \DateTime $cutoff)
    {
        // Prune any old records.
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

        // Add a new Request record and set it up with `requestCount` 1 and `lastRequest` now.
        $requestItem = new Request();
        $requestItem->setIpAddress($ip);
        $requestItem->addRequest();
        $em->persist($requestItem);
    }
}
