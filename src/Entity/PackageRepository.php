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
        $treatAsOldBeforeDate = new \DateTimeImmutable(Package::PROVIDER_GROUP_OLD_CUTOFF);

        $em = $this->getEntityManager();

        // build the groups, trying to keep them of roughly equal numbers of packages
        $year = date('Y');
        $groups = [
            ['group' => 'this-week', 'start' => new \DateTimeImmutable('monday last week')],
            ['group' => $year . '-12', 'start' => new \DateTimeImmutable($year . '-10-01')],
            ['group' => $year . '-09', 'start' => new \DateTimeImmutable($year . '-07-01')],
            ['group' => $year . '-06', 'start' => new \DateTimeImmutable($year . '-04-01')],
            ['group' => $year . '-03', 'start' => new \DateTimeImmutable($year . '-01-01')],
        ];
        for ($y=$year-1; $y>=$treatAsOldBeforeDate->format('Y'); $y--) {
            $groups[] = ['group' => $y, 'start' => new \DateTimeImmutable($y . '-01-01')];
        }

        foreach ($groups as $i => $group) {
            $groups[$i]['start'] = $group['start']->format('Y-m-d 00:00:00');
            if ($i === 0) {
                $groups[$i]['end'] = (new \DateTimeImmutable())->format('Y-m-d 23:59:59');
            } else {
                $groups[$i]['end'] = $groups[$i-1]['start'];
            }
        }

        $query = (new QueryBuilder($em))->update(Package::class, 'p')
            ->set('p.providerGroup', ':group')
            ->where('p.lastCommitted BETWEEN :start AND :end')
            ->getQuery();
        foreach ($groups as $group) {
            $query->execute($group);
        }


        $oldPackagesQuery = (new QueryBuilder($em))->update(Package::class, 'p')
            ->set('p.providerGroup', ':group')
            ->where('p.lastCommitted < :cutoffDate')
            ->getQuery();
        $oldPackagesQuery->execute([
            'group' => 'old',
            'cutoffDate' => $treatAsOldBeforeDate->format('Y-m-d'),
        ]);
    }

    /**
     * Get packages that have never been fetched or have been updated since last
     * being fetched or that are inactive but have been updated in the past 90 days
     * and haven't been fetched in the past 7 days.
     *
     * @return array consisting of count and iterable
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
        $countDql = str_replace('SELECT p', 'SELECT COUNT(1)', $dql);
        $dateFormat = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        // This seems to be how Doctrine wants its 'interval' type bound – not a DateInterval
        $parameters = [
            'twoHours' => '2 hour',
            'threeMonthsAgo' => (new \DateTime())->sub(new \DateInterval('P3M'))->format($dateFormat),
            'oneWeekAgo' => (new \DateTime())->sub(new \DateInterval('P1W'))->format($dateFormat)
        ];
        $query = $entityManager->createQuery($dql)->setParameters($parameters);
        $countQuery = $entityManager->createQuery($countDql)->setParameters($parameters);

        return [$countQuery->getSingleScalarResult(), $query->toIterable()];
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
        $qb = new QueryBuilder($this->getEntityManager());
        $qb = $qb->select('count(p.id)')
            ->from(Package::class, 'p')
            ->where('p INSTANCE OF :className')
            ->andWhere('p.lastFetched < p.lastCommitted')
            ->setParameter('className', $className);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $providerGroupMain Provider group without prefix, e.g. '2020', '2021-03', 'this-week'.
     * @return int
     */
    public function getCountByGroup(string $providerGroupMain): int
    {
        $qb = new QueryBuilder($this->getEntityManager());
        $qb = $qb->select('count(p.id)')
            ->from(Package::class, 'p')
            ->where('p.providerGroup = :providerGroup')
            ->setParameter('providerGroup', $providerGroupMain);

        return $qb->getQuery()->getSingleScalarResult();
    }
}
