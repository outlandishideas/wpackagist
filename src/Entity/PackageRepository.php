<?php

namespace Outlandish\Wpackagist\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PackageRepository extends EntityRepository
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateProviderGroups(): void
    {
        // default everything to 'old'
        $em = $this->getEntityManager();
        $qb = new QueryBuilder($em);
        $qb->update(Package::class, 'p')
            ->set('p.providerGroup', ':group')
            ->where('p.providerGroup <> :group')
            ->setParameter('group', 'old')
            ->getQuery()
            ->execute();

        // build the groups, trying to keep them of roughly equal numbers of packages
        $year = date('Y');
        $groups = [
            'this-week' => new \DateTime('monday last week'),
            $year . '-12' => new \DateTime($year . '-10-01'),
            $year . '-09' => new \DateTime($year . '-07-01'),
            $year . '-06' => new \DateTime($year . '-04-01'),
            $year . '-03' => new \DateTime($year . '-01-01'),
        ];
        for ($y=$year-1; $y>=2011; $y--) {
            $groups[$y] = new \DateTime($y . '-01-01');
        }

        $qb = new QueryBuilder($em);
        $query = $qb->update(Package::class, 'p')
            ->set('p.providerGroup', ':group')
            ->where('p.providerGroup = \'old\'')
            ->andWhere('p.lastCommitted >= :date')
            ->getQuery();
        foreach ($groups as $key => $date) {
            $query->execute(['group' => $key, 'date' => $date->format('Y-m-d')]);
        }
    }

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

        return $qb->getQuery()->getResult();
    }

    public function findActivePackageNamesByGroup($group): array
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

    public function getNewlyRefreshedCount(string $className): int
    {
        $entityManager = $this->getEntityManager();

        $qb = new QueryBuilder($entityManager);
        $qb = $qb->select('count(p.id)')
            ->from(Package::class, 'p')
            ->where('p INSTANCE OF :className')
            ->andWhere('p.lastFetched < p.lastCommitted')
            ->setParameter('className', $className);

        return $qb->getQuery()->getSingleScalarResult();
    }
}
