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
            $this->deleteOldRequestCounts($ip, $oneHourAgo);

            $requestItem = new Request();
            $requestItem->setIpAddress($ip);
        } else {
            $requestItem = $requestHistory[0];
        }

        $requestItem->addRequest();
        // TODO (low priority) this can – very rarely – crash if another request persisted a record
        // with the same IP since the lookup to check for existing ones. Ideally the select and
        // insert would be atomic in a txn?
        $em->persist($requestItem);

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
