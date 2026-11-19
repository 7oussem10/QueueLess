<?php

namespace App\Repository;

use App\Entity\Counter;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Counter>
 */
class CounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Counter::class);
    }

    /**
     * @return Counter[]
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.service', 's')
            ->addSelect('s')
            ->leftJoin('c.currentAgent', 'a')
            ->addSelect('a')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Counter[]
     */
    public function findActiveCountersByService(Service $service): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.service = :service')
            ->setParameter('service', $service)
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
