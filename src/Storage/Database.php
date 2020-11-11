<?php

namespace Outlandish\Wpackagist\Storage;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Outlandish\Wpackagist\Entity\PackageData;

final class Database extends PackageStore
{
    const TYPE_PACKAGE = 'package';
    const TYPE_PROVIDER = 'provider';
    const TYPE_ROOT = 'root';

    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var ObjectRepository */
    private $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function loadEntity($type, $name, $hash): ?string
    {
        $data = $this->getRepository()->findOneBy(['type' => $type, 'name' => $name, 'hash' => $hash]);
        if ($data) {
            return $data->getValue();
        }

        return null;
    }

    /**
     * @param string $packageName
     * @param string $hash
     * @return string|null  Blank if not found.
     */
    public function loadPackage(string $packageName, string $hash): ?string
    {
        return $this->loadEntity(self::TYPE_PACKAGE, $packageName, $hash);
    }

    /**
     * @param string $name
     * @param string $hash
     * @return string|null  Blank if not found.
     */
    public function loadProvider(string $name, string $hash): ?string
    {
        return $this->loadEntity(self::TYPE_PROVIDER, $name, $hash);
    }

    /**
     * @return string|null  Blank if not found.
     */
    public function loadRoot(): ?string
    {
        return $this->loadEntity(self::TYPE_ROOT, '', '');
    }

    public function loadLatestEntities($type, $names = null)
    {
        // we're not interested in the models, only the keyed values, so don't use the repository
        $qb = new QueryBuilder($this->entityManager);
        $qb->select('p.name, p.value')
            ->from(PackageData::class, 'p')
            ->where('p.type = :type')
            ->andWhere('p.isLatest = true')
            ->setParameter('type', $type);
        if ($names) {
            $qb->andWhere($qb->expr()->in('p.name', $names));
        }
        $data = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        $values = [];
        foreach ($data as $datum) {
            $values[$datum['name']] = $datum['value'];
        }

        return $values;
    }

    public function loadAllPackages($packageNames)
    {
        return $this->loadLatestEntities(self::TYPE_PACKAGE, $packageNames);
    }

    public function loadAllProviders()
    {
        return $this->loadLatestEntities(self::TYPE_PROVIDER);
    }

    protected function saveEntity(string $type, string $name, string $hash, string $json): bool
    {
        // Update or insert as needed.
        /** @var PackageData[] $data */
        $data = $this->getRepository()->findBy(['type' => $type, 'name' => $name]);
        $match = null;

        // ensure there is only one 'latest' package data for this entity
        foreach ($data as $datum) {
            if ($datum->getHash() === $hash) {
                $match = $datum;
            } elseif ($datum->getIsLatest()) {
                $datum->setIsLatest(false);
            } else {
                $this->entityManager->detach($datum);
            }
        }

        $changed = false;
        if (!$match) {
            $match = new PackageData();
            $match->setType($type);
            $match->setName($name);
            $match->setHash($hash);
            $changed = true;
            $this->entityManager->persist($match);
        }
        if ($json !== $match->getValue()) {
            $match->setValue($json);
            $changed = true;
        }
        if (!$match->getIsLatest()) {
            $match->setIsLatest(true);
            $changed = true;
        }
        if (!$changed) {
            $this->entityManager->detach($match);
        }

        return true;
    }

    public function savePackage(string $packageName, string $hash, string $json): bool
    {
        return $this->saveEntity(self::TYPE_PACKAGE, $packageName, $hash, $json);
    }


    public function saveProvider(string $name, string $hash, string $json): bool
    {
        return $this->saveEntity(self::TYPE_PROVIDER, $name, $hash, $json);
    }

    public function saveRoot(string $json): bool
    {
        return $this->saveEntity(self::TYPE_ROOT, '', '', $json);
    }

    public function prepare($partial = false): void
    {
    }

    public function persist($final = false): void
    {
        $this->entityManager->flush();

        if ($final) {
            $qb = new QueryBuilder($this->entityManager);
            $qb->delete(PackageData::class, 'p')
                ->where('p.isLatest = false')
                ->getQuery()
                ->execute();
        }
    }

    protected function getRepository(): ObjectRepository
    {
        if (!$this->repository) {
            $this->repository = $this->entityManager->getRepository(PackageData::class);
        }

        return $this->repository;
    }
}
