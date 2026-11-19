<?php

namespace App\Controller\Agent;

use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\CounterRepository;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use App\Service\TicketManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent/tickets')]
#[IsGranted('ROLE_AGENT')]
class TicketController extends AbstractController
{
    /**
     * US15: Consulter les tickets en attente
     */
    #[Route('', name: 'agent_tickets_pending', methods: ['GET'])]
    public function index(
        Request $request,
        TicketRepository $ticketRepository,
        ServiceRepository $serviceRepository,
        CounterRepository $counterRepository
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();
        $assignedCounter = $counterRepository->findOneBy(['currentAgent' => $agent]);

        $serviceId = $request->query->get('service_id');
        $selectedService = $serviceId ? $serviceRepository->find($serviceId) : ($assignedCounter?->getService());

        $pendingTickets = $ticketRepository->findPendingTickets($selectedService);
        $activeTicket = $ticketRepository->findActiveTicketForAgent($agent);
        $allServices = $serviceRepository->findActiveServices();

        return $this->render('agent/ticket/index.html.twig', [
            'pendingTickets' => $pendingTickets,
            'selectedService' => $selectedService,
            'allServices' => $allServices,
            'assignedCounter' => $assignedCounter,
            'activeTicket' => $activeTicket,
        ]);
    }

    /**
     * US16: Appeler le ticket suivant (FIFO)
     */
    #[Route('/call-next', name: 'agent_ticket_call_next', methods: ['POST'])]
    public function callNext(
        Request $request,
        CounterRepository $counterRepository,
        ServiceRepository $serviceRepository,
        TicketManager $ticketManager
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();

        if (!$this->isCsrfTokenValid('call-next-ticket', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('agent_dashboard');
        }

        $assignedCounter = $counterRepository->findOneBy(['currentAgent' => $agent]);
        if (!$assignedCounter) {
            $this->addFlash('warning', 'Veuillez d\'abord choisir et prendre un guichet avant d\'appeler un ticket.');
            return $this->redirectToRoute('agent_dashboard');
        }

        $serviceId = $request->request->get('service_id');
        $service = $serviceId ? $serviceRepository->find($serviceId) : $assignedCounter->getService();

        try {
            $calledTicket = $ticketManager->callNextTicket($agent, $assignedCounter, $service);
            if ($calledTicket) {
                $this->addFlash('success', sprintf('Ticket %s appelé au %s !', $calledTicket->getTicketNumber(), $assignedCounter->getName()));
            } else {
                $this->addFlash('info', 'Aucun ticket en attente pour ce service actuellement.');
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('agent_dashboard');
    }

    /**
     * US17: Consulter les informations d'un ticket
     */
    #[Route('/{id}', name: 'agent_ticket_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Ticket $ticket): Response
    {
        return $this->render('agent/ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * US18: Commencer le traitement d'un ticket
     */
    #[Route('/{id}/start', name: 'agent_ticket_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function start(
        Request $request,
        Ticket $ticket,
        TicketManager $ticketManager
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();

        if (!$this->isCsrfTokenValid('ticket-start-' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('agent_dashboard');
        }

        try {
            $ticketManager->startProcessing($ticket, $agent);
            $this->addFlash('success', sprintf('Traitement du ticket %s commencé.', $ticket->getTicketNumber()));
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('agent_dashboard');
    }

    /**
     * US19: Terminer le traitement d'un ticket
     */
    #[Route('/{id}/finish', name: 'agent_ticket_finish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function finish(
        Request $request,
        Ticket $ticket,
        TicketManager $ticketManager
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();

        if (!$this->isCsrfTokenValid('ticket-finish-' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('agent_dashboard');
        }

        $notes = $request->request->get('notes');

        try {
            $ticketManager->finishProcessing($ticket, $agent, $notes ? (string) $notes : null);
            $this->addFlash('success', sprintf('Traitement du ticket %s terminé avec succès.', $ticket->getTicketNumber()));
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('agent_dashboard');
    }

    /**
     * US20: Marquer un client comme absent
     */
    #[Route('/{id}/mark-absent', name: 'agent_ticket_mark_absent', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAbsent(
        Request $request,
        Ticket $ticket,
        TicketManager $ticketManager
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();

        if (!$this->isCsrfTokenValid('ticket-absent-' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('agent_dashboard');
        }

        try {
            $ticketManager->markAbsent($ticket, $agent);
            $this->addFlash('warning', sprintf('Le client du ticket %s a été marqué absent.', $ticket->getTicketNumber()));
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('agent_dashboard');
    }
}
