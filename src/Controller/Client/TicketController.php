<?php

namespace App\Controller\Client;

use App\Entity\Service;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use App\Service\TicketManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/tickets')]
#[IsGranted('ROLE_CLIENT')]
class TicketController extends AbstractController
{
    /**
     * US13 & List of client tickets (BR04)
     */
    #[Route('', name: 'client_ticket_list', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): Response
    {
        /** @var User $client */
        $client = $this->getUser();
        $tickets = $ticketRepository->findByClient($client);

        return $this->render('client/ticket/index.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * US04: Obtenir un ticket
     */
    #[Route('/request', name: 'client_ticket_request', methods: ['GET', 'POST'])]
    public function requestTicket(
        Request $request,
        ServiceRepository $serviceRepository,
        TicketManager $ticketManager
    ): Response {
        /** @var User $client */
        $client = $this->getUser();
        $services = $serviceRepository->findActiveServices();

        if ($request->isMethod('POST')) {
            $serviceId = (int) $request->request->get('service_id');
            if (!$this->isCsrfTokenValid('request-ticket', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('client_ticket_request');
            }

            $service = $serviceRepository->find($serviceId);
            if (!$service || !$service->isActive()) {
                $this->addFlash('danger', 'Veuillez sélectionner un service valide et actuellement ouvert.');
                return $this->redirectToRoute('client_ticket_request');
            }

            try {
                $ticket = $ticketManager->createTicket($client, $service);
                $this->addFlash('success', sprintf('Ticket numéro %s généré avec succès !', $ticket->getTicketNumber()));

                return $this->redirectToRoute('client_ticket_show', ['id' => $ticket->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('client_ticket_request');
            }
        }

        return $this->render('client/ticket/request.html.twig', [
            'services' => $services,
        ]);
    }

    /**
     * US05, US06: Consulter le numéro du ticket & sa position dans la file (BR04)
     */
    #[Route('/{id}', name: 'client_ticket_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Ticket $ticket, TicketRepository $ticketRepository): Response
    {
        /** @var User $client */
        $client = $this->getUser();

        // BR04: A client can only view their own tickets
        if ($ticket->getClient()->getId() !== $client->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé(e) à consulter ce ticket.');
        }

        $queueInfo = $ticketRepository->getQueuePosition($ticket);
        $estimatedWaitMinutes = $queueInfo['position'] * $ticket->getService()->getEstimatedDurationMinutes();

        return $this->render('client/ticket/show.html.twig', [
            'ticket' => $ticket,
            'position' => $queueInfo['position'],
            'peopleAhead' => $queueInfo['peopleAhead'],
            'estimatedWaitMinutes' => $estimatedWaitMinutes,
        ]);
    }
}
