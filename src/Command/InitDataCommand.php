<?php

namespace App\Command;

use App\Entity\Counter;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:init-data',
    description: 'Initialise les données de démarrage pour QueueLess (utilisateurs, rôles, services, guichets).'
)]
class InitDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userRepo = $this->entityManager->getRepository(User::class);
        $serviceRepo = $this->entityManager->getRepository(Service::class);
        $counterRepo = $this->entityManager->getRepository(Counter::class);

        // 1. Administrateur
        $admin = $userRepo->findOneBy(['email' => 'admin@queueless.local']);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail('admin@queueless.local')
                ->setFirstName('Admin')
                ->setLastName('Système')
                ->setPhoneNumber('0123456789')
                ->setRoles(['ROLE_ADMIN'])
                ->setIsActive(true);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!'));
            $this->entityManager->persist($admin);
            $io->info('Administrateur créé : admin@queueless.local / Admin123!');
        }

        // 2. Agent
        $agent = $userRepo->findOneBy(['email' => 'agent@queueless.local']);
        if (!$agent) {
            $agent = new User();
            $agent->setEmail('agent@queueless.local')
                ->setFirstName('Karim')
                ->setLastName('Ben Salem')
                ->setPhoneNumber('0987654321')
                ->setRoles(['ROLE_AGENT'])
                ->setIsActive(true);
            $agent->setPassword($this->passwordHasher->hashPassword($agent, 'Agent123!'));
            $this->entityManager->persist($agent);
            $io->info('Agent créé : agent@queueless.local / Agent123!');
        }

        // 3. Client
        $client = $userRepo->findOneBy(['email' => 'client@queueless.local']);
        if (!$client) {
            $client = new User();
            $client->setEmail('client@queueless.local')
                ->setFirstName('Sophie')
                ->setLastName('Martin')
                ->setPhoneNumber('0611223344')
                ->setRoles(['ROLE_CLIENT', 'ROLE_USER'])
                ->setIsActive(true);
            $client->setPassword($this->passwordHasher->hashPassword($client, 'Client123!'));
            $this->entityManager->persist($client);
            $io->info('Client créé : client@queueless.local / Client123!');
        }

        $this->entityManager->flush();

        // 4. Services
        $servicesData = [
            [
                'code' => 'ACC',
                'name' => 'Accueil & Information',
                'description' => 'Orientation générale, renseignements administratifs, vérification de dossiers.',
                'duration' => 10,
            ],
            [
                'code' => 'DOC',
                'name' => 'Cartes & Documents Officiels',
                'description' => 'Délivrance de cartes, attestations officielles et certificats.',
                'duration' => 20,
            ],
            [
                'code' => 'FAC',
                'name' => 'Facturation & Paiements',
                'description' => 'Règlement des frais, facturation, encaissement et réclamations financières.',
                'duration' => 15,
            ],
        ];

        $servicesMap = [];
        foreach ($servicesData as $sData) {
            $service = $serviceRepo->findOneBy(['code' => $sData['code']]);
            if (!$service) {
                $service = new Service();
                $service->setCode($sData['code'])
                    ->setName($sData['name'])
                    ->setDescription($sData['description'])
                    ->setEstimatedDurationMinutes($sData['duration'])
                    ->setIsActive(true);
                $this->entityManager->persist($service);
                $io->info('Service créé : ' . $sData['name']);
            }
            $servicesMap[$sData['code']] = $service;
        }

        $this->entityManager->flush();

        // 5. Guichets
        $countersData = [
            ['code' => 'G1', 'name' => 'Guichet 1 - Accueil', 'serviceCode' => 'ACC', 'agent' => $agent],
            ['code' => 'G2', 'name' => 'Guichet 2 - Documents', 'serviceCode' => 'DOC', 'agent' => null],
            ['code' => 'G3', 'name' => 'Guichet 3 - Caisse', 'serviceCode' => 'FAC', 'agent' => null],
        ];

        foreach ($countersData as $cData) {
            $counter = $counterRepo->findOneBy(['code' => $cData['code']]);
            if (!$counter) {
                $counter = new Counter();
                $counter->setCode($cData['code'])
                    ->setName($cData['name'])
                    ->setService($servicesMap[$cData['serviceCode']] ?? null)
                    ->setCurrentAgent($cData['agent'])
                    ->setIsActive(true);
                $this->entityManager->persist($counter);
                $io->info('Guichet créé : ' . $cData['name']);
            }
        }

        $this->entityManager->flush();

        // 6. Horaires des services (US28)
        $scheduleRepo = $this->entityManager->getRepository(\App\Entity\ServiceSchedule::class);
        foreach ($servicesMap as $service) {
            for ($day = 1; $day <= 5; $day++) {
                $sched = $scheduleRepo->findOneBy(['service' => $service, 'dayOfWeek' => $day]);
                if (!$sched) {
                    $sched = new \App\Entity\ServiceSchedule();
                    $sched->setService($service)
                        ->setDayOfWeek($day)
                        ->setOpeningTime(new \DateTime('08:30:00'))
                        ->setClosingTime(new \DateTime('16:30:00'))
                        ->setSlotDurationMinutes(20)
                        ->setIsActive(true);
                    $this->entityManager->persist($sched);
                }
            }
        }

        $this->entityManager->flush();

        $io->success('Initialisation des données terminée avec succès.');

        return Command::SUCCESS;
    }
}
