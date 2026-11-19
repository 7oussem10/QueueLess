<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\ServiceScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AppointmentRepository $appointmentRepository,
        private ServiceScheduleRepository $scheduleRepository
    ) {
    }

    /**
     * US08: Consulter les créneaux disponibles pour un service et une date (BR10)
     *
     * @return array<int, array{startTime: \DateTimeInterface, endTime: \DateTimeInterface, timeString: string, isAvailable: bool}>
     */
    public function getAvailableSlots(Service $service, \DateTimeInterface $date): array
    {
        $today = new \DateTimeImmutable('today');
        if (!$service->isActive() || $date->format('Y-m-d') < $today->format('Y-m-d')) {
            return [];
        }

        $dayOfWeek = (int) $date->format('N'); // 1 (Mon) to 7 (Sun)
        $schedule = $this->scheduleRepository->findScheduleForDay($service, $dayOfWeek);

        if (!$schedule || !$schedule->isActive()) {
            return [];
        }

        $slots = [];
        $openingMinutes = $this->minutesSinceMidnight($schedule->getOpeningTime());
        $closingMinutes = $this->minutesSinceMidnight($schedule->getClosingTime());
        $durationMinutes = $schedule->getSlotDurationMinutes();
        $isToday = $date->format('Y-m-d') === $today->format('Y-m-d');
        $currentMinutes = $this->minutesSinceMidnight(new \DateTimeImmutable());

        for ($startMinutes = $openingMinutes; $startMinutes + $durationMinutes <= $closingMinutes; $startMinutes += $durationMinutes) {
            if ($isToday && $startMinutes <= $currentMinutes) {
                continue;
            }

            $currentStart = $this->timeFromMinutes($startMinutes);
            $currentEnd = $this->timeFromMinutes($startMinutes + $durationMinutes);

            // Check if slot is already reserved (BR07, BR09)
            $existing = $this->appointmentRepository->findConflictingAppointment($service, $date, $currentStart);

            $slots[] = [
                'startTime' => clone $currentStart,
                'endTime' => clone $currentEnd,
                'timeString' => $currentStart->format('H:i') . ' - ' . $currentEnd->format('H:i'),
                'isAvailable' => $existing === null,
            ];

        }

        return $slots;
    }

    /**
     * US09: Réserver un rendez-vous (BR07, BR10, BR11)
     */
    public function bookAppointment(
        User $client,
        Service $service,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        ?string $notes = null
    ): Appointment {
        $today = new \DateTimeImmutable('today');
        if ($date->format('Y-m-d') < $today->format('Y-m-d')) {
            throw new \InvalidArgumentException('Un rendez-vous ne peut pas être réservé dans le passé.');
        }

        if (!$service->isActive()) {
            throw new \InvalidArgumentException('Ce service est actuellement fermé.');
        }

        // 1. Verify service schedule (BR10, BR11)
        $dayOfWeek = (int) $date->format('N');
        $schedule = $this->scheduleRepository->findScheduleForDay($service, $dayOfWeek);
        if (!$schedule || !$schedule->isActive()) {
            throw new \InvalidArgumentException('Le service est fermé à cette date.');
        }

        $openingMinutes = $this->minutesSinceMidnight($schedule->getOpeningTime());
        $closingMinutes = $this->minutesSinceMidnight($schedule->getClosingTime());
        $slotMinutes = $this->minutesSinceMidnight($startTime);
        $duration = $schedule->getSlotDurationMinutes();

        if ($slotMinutes < $openingMinutes || $slotMinutes + $duration > $closingMinutes) {
            throw new \InvalidArgumentException('Le créneau sélectionné est en dehors des horaires d\'ouverture du service.');
        }

        if (($slotMinutes - $openingMinutes) % $duration !== 0) {
            throw new \InvalidArgumentException('Le créneau sélectionné n\'est pas disponible. Veuillez choisir un horaire proposé.');
        }

        if ($date->format('Y-m-d') === $today->format('Y-m-d') && $slotMinutes <= $this->minutesSinceMidnight(new \DateTimeImmutable())) {
            throw new \InvalidArgumentException('Un rendez-vous ne peut pas être réservé à une heure déjà passée.');
        }

        // 2. Prevent duplicate booking for the same slot (BR07)
        $conflicting = $this->appointmentRepository->findConflictingAppointment($service, $date, $startTime);
        if ($conflicting) {
            throw new \LogicException('Ce créneau horaire est déjà réservé par un autre usager. Veuillez choisir un autre horaire.');
        }

        // 3. Calculate endTime
        $endTime = $this->timeFromMinutes($slotMinutes + $duration);

        // 4. Create appointment
        $appointment = new Appointment();
        $appointment->setClient($client);
        $appointment->setService($service);
        $appointment->setAppointmentDate($date);
        $appointment->setStartTime($startTime);
        $appointment->setEndTime($endTime);
        $appointment->setStatus(Appointment::STATUS_CONFIRMED);
        $appointment->setNotes($notes);

        // Generate unique reference: RDV-<CODE>-YYYYMMDD-XXX
        $count = $this->appointmentRepository->countAppointmentsForServiceAndDate($service, $date);
        $ref = sprintf('RDV-%s-%s-%03d', $service->getCode(), $date->format('Ymd'), $count + 1);
        while ($this->appointmentRepository->findOneBy(['appointmentReference' => $ref])) {
            $count++;
            $ref = sprintf('RDV-%s-%s-%03d', $service->getCode(), $date->format('Ymd'), $count + 1);
        }
        $appointment->setAppointmentReference($ref);

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        return $appointment;
    }

    private function minutesSinceMidnight(\DateTimeInterface $time): int
    {
        return ((int) $time->format('H') * 60) + (int) $time->format('i');
    }

    private function timeFromMinutes(int $minutes): \DateTime
    {
        $time = new \DateTime('today');

        return $time->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * US11, BR08, BR09: Annuler un rendez-vous et libérer le créneau
     */
    public function cancelAppointment(Appointment $appointment, User $user): void
    {
        // BR08: Only the client or an administrator/agent can cancel
        $isOwner = $appointment->getClient()->getId() === $user->getId();
        $isStaff = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_AGENT', $user->getRoles(), true);

        if (!$isOwner && !$isStaff) {
            throw new \LogicException('Vous n\'êtes pas autorisé(e) à annuler ce rendez-vous.');
        }

        if ($appointment->getStatus() === Appointment::STATUS_CANCELLED) {
            return;
        }

        $appointment->setStatus(Appointment::STATUS_CANCELLED);
        $appointment->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
