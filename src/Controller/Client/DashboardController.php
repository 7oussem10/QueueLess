<?php

namespace App\Controller\Client;

use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_CLIENT')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'client_dashboard')]
    public function index(ServiceRepository $serviceRepository, TicketRepository $ticketRepository): Response
    {
        /** @var User $client */
        $client = $this->getUser();
        $services = $serviceRepository->findActiveServices();
        $allTickets = $ticketRepository->findByClient($client);

        $activeTickets = [];
        $pastTickets = [];

        foreach ($allTickets as $t) {
            if (in_array($t->getStatus(), [Ticket::STATUS_WAITING, Ticket::STATUS_CALLED, Ticket::STATUS_IN_PROGRESS], true)) {
                $queueInfo = $ticketRepository->getQueuePosition($t);
                $activeTickets[] = [
                    'ticket' => $t,
                    'position' => $queueInfo['position'],
                    'peopleAhead' => $queueInfo['peopleAhead'],
                    'estimatedWaitMinutes' => $queueInfo['position'] * $t->getService()->getEstimatedDurationMinutes(),
                ];
            } else {
                $pastTickets[] = $t;
            }
        }

        return $this->render('client/dashboard/index.html.twig', [
            'client' => $client,
            'services' => $services,
            'activeTickets' => $activeTickets,
            'pastTickets' => array_slice($pastTickets, 0, 5),
        ]);
    }
}
