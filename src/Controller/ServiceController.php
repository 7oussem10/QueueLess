<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/services')]
class ServiceController extends AbstractController
{
    #[Route('', name: 'app_services_list', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        $services = $serviceRepository->findActiveServices();

        return $this->render('service/index.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/{id}', name: 'app_service_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }
}
