<?php

namespace App\Controller\Admin;

use App\Repository\AppointmentRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/appointments')]
#[IsGranted('ROLE_ADMIN')]
class AppointmentController extends AbstractController
{
    /**
     * US21: Consulter les rendez-vous du jour
     */
    #[Route('/today', name: 'admin_appointments_today', methods: ['GET'])]
    public function today(
        Request $request,
        AppointmentRepository $appointmentRepository,
        ServiceRepository $serviceRepository
    ): Response {
        $today = new \DateTime('today');
        $dateParam = $request->query->get('date');
        $date = $dateParam ? (\DateTime::createFromFormat('Y-m-d', $dateParam) ?: $today) : $today;

        $serviceId = $request->query->get('service_id');
        $selectedService = $serviceId ? $serviceRepository->find($serviceId) : null;

        $appointments = $appointmentRepository->findDailyAppointments($date, $selectedService);
        $allServices = $serviceRepository->findAll();

        return $this->render('admin/appointment/today.html.twig', [
            'appointments' => $appointments,
            'selectedDate' => $date,
            'selectedService' => $selectedService,
            'allServices' => $allServices,
        ]);
    }
}
