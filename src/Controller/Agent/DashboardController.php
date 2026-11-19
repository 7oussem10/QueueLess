<?php

namespace App\Controller\Agent;

use App\Entity\User;
use App\Repository\CounterRepository;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'agent_dashboard')]
    public function index(
        CounterRepository $counterRepository,
        ServiceRepository $serviceRepository,
        TicketRepository $ticketRepository
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();
        $assignedCounter = $counterRepository->findOneBy(['currentAgent' => $agent]);
        $allActiveCounters = $counterRepository->findBy(['isActive' => true]);
        $services = $serviceRepository->findActiveServices();

        // Active ticket currently handled by the agent
        $activeTicket = $ticketRepository->findActiveTicketForAgent($agent);

        // Pending tickets for this counter's service
        $pendingTickets = [];
        if ($assignedCounter && $assignedCounter->getService()) {
            $pendingTickets = $ticketRepository->findPendingTickets($assignedCounter->getService());
        } elseif ($assignedCounter) {
            $pendingTickets = $ticketRepository->findPendingTickets();
        }

        return $this->render('agent/dashboard/index.html.twig', [
            'agent' => $agent,
            'assignedCounter' => $assignedCounter,
            'allActiveCounters' => $allActiveCounters,
            'services' => $services,
            'activeTicket' => $activeTicket,
            'pendingTickets' => $pendingTickets,
        ]);
    }

    #[Route('/assign-counter', name: 'agent_assign_counter', methods: ['POST'])]
    public function assignCounter(
        Request $request,
        CounterRepository $counterRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $agent */
        $agent = $this->getUser();
        $counterId = $request->request->get('counter_id');

        if (!$this->isCsrfTokenValid('agent-assign-counter', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('agent_dashboard');
        }

        // Release any existing counter currently assigned to this agent
        $currentCounters = $counterRepository->findBy(['currentAgent' => $agent]);
        foreach ($currentCounters as $c) {
            $c->setCurrentAgent(null);
        }

        if ($counterId) {
            $counter = $counterRepository->find($counterId);
            if ($counter && $counter->isActive()) {
                $counter->setCurrentAgent($agent);
                $this->addFlash('success', sprintf('Vous êtes maintenant connecté(e) au "%s".', $counter->getName()));
            }
        } else {
            $this->addFlash('info', 'Vous vous êtes déconnecté(e) de votre guichet.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('agent_dashboard');
    }
}
