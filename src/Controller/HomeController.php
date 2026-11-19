<?php

namespace App\Controller;

use App\Repository\CounterRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ServiceRepository $serviceRepository, CounterRepository $counterRepository): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($this->isGranted('ROLE_AGENT')) {
                return $this->redirectToRoute('agent_dashboard');
            }
            return $this->redirectToRoute('client_dashboard');
        }

        $services = $serviceRepository->findActiveServices();
        $counters = $counterRepository->findAll();

        return $this->render('home/index.html.twig', [
            'services' => $services,
            'countersCount' => count($counters),
        ]);
    }
}
