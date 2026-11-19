<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\ServiceSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceSchedule>
 */
class ServiceScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceSchedule::class);
    }

    /**
     * @return ServiceSchedule[]
     */
    public function findByService(Service $service): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.service = :service')
            ->setParameter('service', $service)
            ->orderBy('s.dayOfWeek', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active schedule for a service on a given day of the week (1..7).
     */
    public function findScheduleForDay(Service $service, int $dayOfWeek): ?ServiceSchedule
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.service = :service')
            ->setParameter('service', $service)
            ->andWhere('s.dayOfWeek = :day')
            ->setParameter('day', $dayOfWeek)
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
