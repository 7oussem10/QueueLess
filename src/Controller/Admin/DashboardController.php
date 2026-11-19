<?php

namespace App\Controller\Admin;

use App\Repository\CounterRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        CounterRepository $counterRepository
    ): Response {
        $totalUsers = $userRepository->count([]);
        $agentsCount = count($userRepository->findAgents());
        $activeServicesCount = count($serviceRepository->findBy(['isActive' => true]));
        $totalServicesCount = $serviceRepository->count([]);
        $activeCountersCount = count($counterRepository->findBy(['isActive' => true]));
        $totalCountersCount = $counterRepository->count([]);

        $recentUsers = $userRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $services = $serviceRepository->findAll();
        $counters = $counterRepository->findAllWithRelations();

        return $this->render('admin/dashboard/index.html.twig', [
            'totalUsers' => $totalUsers,
            'agentsCount' => $agentsCount,
            'activeServicesCount' => $activeServicesCount,
            'totalServicesCount' => $totalServicesCount,
            'activeCountersCount' => $activeCountersCount,
            'totalCountersCount' => $totalCountersCount,
            'recentUsers' => $recentUsers,
            'services' => $services,
            'counters' => $counters,
        ]);
    }
}
