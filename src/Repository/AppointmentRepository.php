<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * US21: Consulter les rendez-vous du jour avec filtre par service
     *
     * @return Appointment[]
     */
    public function findDailyAppointments(\DateTimeInterface $date, ?Service $service = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->leftJoin('a.client', 'c')
            ->addSelect('c')
            ->leftJoin('a.counter', 'k')
            ->addSelect('k')
            ->andWhere('a.appointmentDate = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('a.startTime', 'ASC');

        if ($service) {
            $qb->andWhere('a.service = :service')
               ->setParameter('service', $service);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * US10, BR08: Consulter ses rendez-vous (Client)
     *
     * @return Appointment[]
     */
    public function findByClient(User $client): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.service', 's')
            ->addSelect('s')
            ->leftJoin('a.counter', 'c')
            ->addSelect('c')
            ->andWhere('a.client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.appointmentDate', 'DESC')
            ->addOrderBy('a.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * BR07, BR09: Check if a slot is already reserved by another client.
     * Note: Cancelled appointments do NOT block the slot!
     */
    public function findConflictingAppointment(
        Service $service,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime
    ): ?Appointment {
        return $this->createQueryBuilder('a')
            ->andWhere('a.service = :service')
            ->setParameter('service', $service)
            ->andWhere('a.appointmentDate = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->andWhere('a.startTime = :time')
            ->setParameter('time', $startTime->format('H:i:s'))
            ->andWhere('a.status = :status')
            ->setParameter('status', Appointment::STATUS_CONFIRMED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count appointments created for date and service to generate reference.
     */
    public function countAppointmentsForServiceAndDate(Service $service, \DateTimeInterface $date): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.service = :service')
            ->setParameter('service', $service)
            ->andWhere('a.appointmentDate = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
