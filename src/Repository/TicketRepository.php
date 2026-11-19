<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @return Ticket[]
     */
    public function findPendingTickets(?Service $service = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.service', 's')
            ->addSelect('s')
            ->leftJoin('t.client', 'c')
            ->addSelect('c')
            ->andWhere('t.status = :status')
            ->setParameter('status', Ticket::STATUS_WAITING)
            ->orderBy('t.createdAt', 'ASC');

        if ($service) {
            $qb->andWhere('t.service = :service')
               ->setParameter('service', $service);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find next pending ticket for a service (FIFO).
     */
    public function findNextPendingTicket(Service $service): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.service = :service')
            ->setParameter('service', $service)
            ->andWhere('t.status = :status')
            ->setParameter('status', Ticket::STATUS_WAITING)
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get queue position and number of people ahead for a waiting ticket.
     *
     * @return array{position: int, peopleAhead: int}
     */
    public function getQueuePosition(Ticket $ticket): array
    {
        if ($ticket->getStatus() !== Ticket::STATUS_WAITING) {
            return ['position' => 0, 'peopleAhead' => 0];
        }

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.service = :service')
            ->setParameter('service', $ticket->getService())
            ->andWhere('t.status = :status')
            ->setParameter('status', Ticket::STATUS_WAITING)
            ->andWhere('t.createdAt < :createdAt OR (t.createdAt = :createdAt AND t.id < :ticketId)')
            ->setParameter('createdAt', $ticket->getCreatedAt())
            ->setParameter('ticketId', $ticket->getId());

        $peopleAhead = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'position' => $peopleAhead + 1,
            'peopleAhead' => $peopleAhead,
        ];
    }

    /**
     * Find active ticket currently being handled by an agent (called or in_progress).
     */
    public function findActiveTicketForAgent(User $agent): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.agent = :agent')
            ->setParameter('agent', $agent)
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('statuses', [Ticket::STATUS_CALLED, Ticket::STATUS_IN_PROGRESS])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count tickets created today for a service.
     */
    public function countTicketsCreatedToday(Service $service): int
    {
        $todayStart = new \DateTimeImmutable('today midnight');
        $todayEnd = new \DateTimeImmutable('tomorrow midnight');

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.service = :service')
            ->setParameter('service', $service)
            ->andWhere('t.createdAt >= :start AND t.createdAt < :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Ticket[]
     */
    public function findByClient(User $client): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.service', 's')
            ->addSelect('s')
            ->leftJoin('t.counter', 'c')
            ->addSelect('c')
            ->andWhere('t.client = :client')
            ->setParameter('client', $client)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
