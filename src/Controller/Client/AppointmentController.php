<?php

namespace App\Controller\Client;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\ServiceRepository;
use App\Service\AppointmentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/appointments')]
#[IsGranted('ROLE_CLIENT')]
class AppointmentController extends AbstractController
{
    /**
     * US10, BR08: Consulter ses rendez-vous
     */
    #[Route('', name: 'client_appointment_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        /** @var User $client */
        $client = $this->getUser();
        $appointments = $appointmentRepository->findByClient($client);

        $upcoming = [];
        $past = [];

        foreach ($appointments as $apt) {
            if ($apt->isUpcoming()) {
                $upcoming[] = $apt;
            } else {
                $past[] = $apt;
            }
        }

        return $this->render('client/appointment/index.html.twig', [
            'upcomingAppointments' => $upcoming,
            'pastAppointments' => $past,
        ]);
    }

    /**
     * US08: Consulter les créneaux disponibles
     */
    #[Route('/slots', name: 'client_appointment_slots', methods: ['GET'])]
    public function slots(
        Request $request,
        ServiceRepository $serviceRepository,
        AppointmentManager $appointmentManager
    ): Response {
        $services = $serviceRepository->findActiveServices();

        $serviceId = $request->query->get('service_id');
        $dateStr = $request->query->get('date', (new \DateTime('+1 day'))->format('Y-m-d'));

        $selectedService = $serviceId ? $serviceRepository->find($serviceId) : ($services[0] ?? null);
        $selectedDate = \DateTime::createFromFormat('Y-m-d', $dateStr) ?: new \DateTime('+1 day');

        $slots = [];
        if ($selectedService) {
            $slots = $appointmentManager->getAvailableSlots($selectedService, $selectedDate);
        }

        return $this->render('client/appointment/slots.html.twig', [
            'services' => $services,
            'selectedService' => $selectedService,
            'selectedDate' => $selectedDate,
            'slots' => $slots,
        ]);
    }

    /**
     * US09: Réserver un rendez-vous (BR07, BR10, BR11)
     */
    #[Route('/book', name: 'client_appointment_book', methods: ['POST'])]
    public function book(
        Request $request,
        ServiceRepository $serviceRepository,
        AppointmentManager $appointmentManager
    ): Response {
        /** @var User $client */
        $client = $this->getUser();

        if (!$this->isCsrfTokenValid('book-appointment', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('client_appointment_slots');
        }

        $serviceId = (int) $request->request->get('service_id');
        $dateStr = (string) $request->request->get('date');
        $timeStr = (string) $request->request->get('time');
        $notes = $request->request->get('notes');

        $service = $serviceRepository->find($serviceId);
        if (!$service || !$service->isActive()) {
            $this->addFlash('danger', 'Service invalide.');
            return $this->redirectToRoute('client_appointment_slots');
        }

        $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        $time = \DateTime::createFromFormat('H:i', $timeStr);

        if (!$date || !$time) {
            $this->addFlash('danger', 'Date ou créneau horaire invalide.');
            return $this->redirectToRoute('client_appointment_slots', ['service_id' => $serviceId, 'date' => $dateStr]);
        }

        try {
            $appointment = $appointmentManager->bookAppointment($client, $service, $date, $time, $notes ? (string) $notes : null);
            $this->addFlash('success', sprintf(
                'Votre rendez-vous pour le service %s a été confirmé le %s à %s (Réf : %s) !',
                $service->getName(),
                $date->format('d/m/Y'),
                $time->format('H:i'),
                $appointment->getAppointmentReference()
            ));

            return $this->redirectToRoute('client_appointment_show', ['id' => $appointment->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('client_appointment_slots', ['service_id' => $serviceId, 'date' => $dateStr]);
        }
    }

    /**
     * US10, BR08: Consulter les détails d'un rendez-vous
     */
    #[Route('/{id}', name: 'client_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Appointment $appointment): Response
    {
        /** @var User $client */
        $client = $this->getUser();

        // BR08: Client can only view their own appointments
        if ($appointment->getClient()->getId() !== $client->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé(e) à consulter ce rendez-vous.');
        }

        return $this->render('client/appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * US11, BR08, BR09: Annuler un rendez-vous
     */
    #[Route('/{id}/cancel', name: 'client_appointment_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(
        Request $request,
        Appointment $appointment,
        AppointmentManager $appointmentManager
    ): Response {
        /** @var User $client */
        $client = $this->getUser();

        if (!$this->isCsrfTokenValid('cancel-appointment-' . $appointment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('client_appointment_index');
        }

        try {
            $appointmentManager->cancelAppointment($appointment, $client);
            $this->addFlash('success', sprintf('Le rendez-vous %s a été annulé. Le créneau a été libéré.', $appointment->getAppointmentReference()));
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('client_appointment_index');
    }
}
