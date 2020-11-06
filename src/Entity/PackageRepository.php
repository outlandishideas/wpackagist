<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PackageRepository extends EntityRepository
{
    /**
     * Get packages that have never been fetched or have been updated since last
     * being fetched or that are inactive but have been updated in the past 90 days
     * and haven't been fetched in the past 7 days.
     *
     * @return Package[]
     */
    public function findDueUpdate(): array
    {
        $entityManager = $this->getEntityManager();
        $dql = <<<EOT
            SELECT p
            FROM Outlandish\Wpackagist\Entity\Package p
            WHERE p.lastFetched IS NULL
                OR (p.lastCommitted - p.lastFetched) > :twoHours
                OR (p.isActive = false AND p.lastCommitted > :threeMonthsAgo AND p.lastFetched < :oneWeekAgo)
EOT;
        $dateFormat = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        $query = $entityManager->createQuery($dql)
            // This seems to be how Doctrine wants its 'interval' type bound – not a DateInterval
            ->setParameter('twoHours', '2 hour')
            ->setParameter('threeMonthsAgo', (new \DateTime())->sub(new \DateInterval('P3M'))->format($dateFormat))
            ->setParameter('oneWeekAgo', (new \DateTime())->sub(new \DateInterval('P1W'))->format($dateFormat));

        return $query->getResult();
    }

    /**
     * @param string|null $packageName
     * @return Package[]
     */
    public function findActive(?string $packageName = null): array
    {
        $entityManager = $this->getEntityManager();

        $qb = new QueryBuilder($entityManager);
        $qb = $qb->select('p')
            ->from(Package::class, 'p')
            ->where('p.versions IS NOT NULL')
            ->andWhere('p.isActive = true');

        if ($packageName) {
            $qb = $qb->andWhere('p.name = :name')
                ->setParameter('name', $packageName);
        }

        $qb = $qb->orderBy('p.name', 'ASC');
//$qb->setMaxResults(100);
        return $qb->getQuery()->getResult();
    }

    public function findActivePackageNamesByGroup($group)
    {
        $entityManager = $this->getEntityManager();

        $qb = new QueryBuilder($entityManager);
        $qb = $qb->select('partial p.{id, name}')
            ->from(Package::class, 'p')
            ->where('p.versions IS NOT NULL')
            ->andWhere('p.providerGroup = :group')
            ->andWhere('p.isActive = true');
        $qb->setParameter('group', $group);

        $packages = $qb->getQuery()->getResult();
        return array_map(function (Package $package) {
            return $package->getPackageName();
        }, $packages);
    }
}
