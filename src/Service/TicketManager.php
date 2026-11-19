<?php

namespace App\Service;

use App\Entity\Counter;
use App\Entity\Service;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

class TicketManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository
    ) {
    }

    /**
     * Create a new ticket for a client (US04, BR01).
     */
    public function createTicket(User $client, Service $service): Ticket
    {
        if (!$service->isActive()) {
            throw new \InvalidArgumentException('Ce service est actuellement fermé.');
        }

        $ticket = new Ticket();
        $ticket->setClient($client);
        $ticket->setService($service);
        $ticket->setStatus(Ticket::STATUS_WAITING);

        // Generate unique ticket number: <CODE>-YYYYMMDD-XXX
        $todayStr = (new \DateTimeImmutable())->format('Ymd');
        $countToday = $this->ticketRepository->countTicketsCreatedToday($service);
        $sequence = $countToday + 1;
        $ticketNumber = sprintf('%s-%s-%03d', $service->getCode(), $todayStr, $sequence);

        // Safety check for uniqueness (BR01)
        while ($this->ticketRepository->findOneBy(['ticketNumber' => $ticketNumber])) {
            $sequence++;
            $ticketNumber = sprintf('%s-%s-%03d', $service->getCode(), $todayStr, $sequence);
        }

        $ticket->setTicketNumber($ticketNumber);

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return $ticket;
    }

    /**
     * Call the next ticket in FIFO order (US16, BR03, BR06).
     */
    public function callNextTicket(User $agent, Counter $counter, ?Service $service = null): ?Ticket
    {
        // 1. If an active ticket is already in progress/called by this agent, prevent calling another one
        $activeTicket = $this->ticketRepository->findActiveTicketForAgent($agent);
        if ($activeTicket) {
            throw new \LogicException(sprintf('Vous traitez déjà le ticket %s. Veuillez terminer ce ticket ou le marquer absent avant d\'en appeler un autre.', $activeTicket->getTicketNumber()));
        }

        // 2. Determine authorized service (BR03)
        $targetService = $service ?? $counter->getService();
        if (!$targetService) {
            throw new \InvalidArgumentException('Aucun service valide n\'est associé à ce guichet.');
        }

        // If counter has an assigned service, ensure agent only calls for that service (BR03)
        if ($counter->getService() && $counter->getService()->getId() !== $targetService->getId()) {
            throw new \LogicException('Ce guichet est restreint au service ' . $counter->getService()->getName() . '.');
        }

        // 3. Find next pending ticket FIFO (BR06)
        $nextTicket = $this->ticketRepository->findNextPendingTicket($targetService);
        if (!$nextTicket) {
            return null;
        }

        $nextTicket->setStatus(Ticket::STATUS_CALLED);
        $nextTicket->setAgent($agent);
        $nextTicket->setCounter($counter);
        $nextTicket->setCalledAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $nextTicket;
    }

    /**
     * Start processing a ticket (US18).
     */
    public function startProcessing(Ticket $ticket, User $agent): void
    {
        if ($ticket->getStatus() !== Ticket::STATUS_CALLED) {
            throw new \LogicException('Seul un ticket avec le statut "Appelé" peut être pris en charge.');
        }

        if ($ticket->getAgent()?->getId() !== $agent->getId()) {
            throw new \LogicException('Ce ticket a été appelé par un autre agent.');
        }

        $ticket->setStatus(Ticket::STATUS_IN_PROGRESS);
        $ticket->setStartedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Finish processing a ticket (US19, BR02).
     */
    public function finishProcessing(Ticket $ticket, User $agent, ?string $notes = null): void
    {
        if (!in_array($ticket->getStatus(), [Ticket::STATUS_CALLED, Ticket::STATUS_IN_PROGRESS], true)) {
            throw new \LogicException('Ce ticket n\'est pas en cours de traitement.');
        }

        if ($ticket->getAgent()?->getId() !== $agent->getId()) {
            throw new \LogicException('Vous n\'êtes pas l\'agent en charge de ce ticket.');
        }

        $ticket->setStatus(Ticket::STATUS_COMPLETED);
        $ticket->setCompletedAt(new \DateTimeImmutable());
        if ($notes) {
            $ticket->setNotes($notes);
        }

        $this->entityManager->flush();
    }

    /**
     * Mark client as absent (US20).
     */
    public function markAbsent(Ticket $ticket, User $agent): void
    {
        if ($ticket->getStatus() !== Ticket::STATUS_CALLED) {
            throw new \LogicException('Seul un ticket appelé peut être marqué comme absent.');
        }

        if ($ticket->getAgent()?->getId() !== $agent->getId()) {
            throw new \LogicException('Vous n\'êtes pas l\'agent qui a appelé ce ticket.');
        }

        $ticket->setStatus(Ticket::STATUS_ABSENT);
        $ticket->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
