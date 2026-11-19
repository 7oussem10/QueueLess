<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Form\ServiceFormType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    #[Route('', name: 'admin_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        return $this->render('admin/service/index.html.twig', [
            'services' => $serviceRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceFormType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($service);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le service "%s" a été créé avec succès.', $service->getName()));

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render('admin/service/new.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_service_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceFormType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le service "%s" a été modifié avec succès.', $service->getName()));

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_service_toggle_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleActive(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('toggle-active-service-' . $service->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_service_index');
        }

        $service->setIsActive(!$service->isActive());
        $service->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $status = $service->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Le service "%s" a été %s.', $service->getName(), $status));

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/{id}/delete', name: 'admin_service_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-service-' . $service->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_service_index');
        }

        if ($service->getCounters()->count() > 0) {
            $this->addFlash('warning', sprintf('Impossible de supprimer le service "%s" car des guichets y sont encore rattachés. Veuillez d\'abord détacher ou réassigner ces guichets.', $service->getName()));
            return $this->redirectToRoute('admin_service_index');
        }

        $entityManager->remove($service);
        $entityManager->flush();

        $this->addFlash('success', 'Le service a été supprimé.');

        return $this->redirectToRoute('admin_service_index');
    }
}
