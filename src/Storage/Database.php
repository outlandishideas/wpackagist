<?php

namespace Outlandish\Wpackagist\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Outlandish\Wpackagist\Entity\PackageData;

final class Database extends Provider
{
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var ObjectRepository */
    private $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function load(string $key): ?string
    {
        $data = $this->loadFromDb($key);
        if ($data) {
            return $data->getValue();
        }

        return null;
    }

    public function save(string $key, string $json): bool
    {
        // Update or insert as needed.
        $data = $this->loadFromDb($key);
        if (!$data) {
            $data = new PackageData();
        }

        $data->setKey($key);
        $data->setValue($json);
        $this->entityManager->persist($data);

        return true;
    }

    public function finalise(): void
    {
        $this->entityManager->flush();
    }

    protected function loadFromDb(string $key): ?PackageData
    {
        return $this->getRepository()->findOneBy(['key' => $key]);
    }

    protected function getRepository(): ObjectRepository
    {
        if (!$this->repository) {
            $this->repository = $this->entityManager->getRepository(PackageData::class);
        }

        return $this->repository;
    }
}
