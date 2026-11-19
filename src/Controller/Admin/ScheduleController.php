<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Entity\ServiceSchedule;
use App\Form\ServiceScheduleFormType;
use App\Repository\ServiceRepository;
use App\Repository\ServiceScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/schedules')]
#[IsGranted('ROLE_ADMIN')]
class ScheduleController extends AbstractController
{
    #[Route('', name: 'admin_schedule_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository, ServiceScheduleRepository $scheduleRepository): Response
    {
        $services = $serviceRepository->findAll();
        $schedulesByService = [];

        foreach ($services as $service) {
            $schedulesByService[$service->getId()] = [
                'service' => $service,
                'schedules' => $scheduleRepository->findByService($service),
            ];
        }

        return $this->render('admin/schedule/index.html.twig', [
            'schedulesByService' => $schedulesByService,
        ]);
    }

    #[Route('/service/{id}', name: 'admin_schedule_by_service', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function byService(Service $service, ServiceScheduleRepository $scheduleRepository): Response
    {
        $schedules = $scheduleRepository->findByService($service);

        return $this->render('admin/schedule/by_service.html.twig', [
            'service' => $service,
            'schedules' => $schedules,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_schedule_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ServiceSchedule $schedule,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(ServiceScheduleFormType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Les horaires du service %s pour le %s ont été mis à jour.',
                $schedule->getService()->getName(),
                $schedule->getDayName()
            ));

            return $this->redirectToRoute('admin_schedule_by_service', ['id' => $schedule->getService()->getId()]);
        }

        return $this->render('admin/schedule/edit.html.twig', [
            'schedule' => $schedule,
            'form' => $form->createView(),
        ]);
    }
}
