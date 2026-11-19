<?php

namespace App\Controller\Admin;

use App\Entity\Counter;
use App\Form\CounterFormType;
use App\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/counters')]
#[IsGranted('ROLE_ADMIN')]
class CounterController extends AbstractController
{
    #[Route('', name: 'admin_counter_index', methods: ['GET'])]
    public function index(CounterRepository $counterRepository): Response
    {
        return $this->render('admin/counter/index.html.twig', [
            'counters' => $counterRepository->findAllWithRelations(),
        ]);
    }

    #[Route('/new', name: 'admin_counter_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $counter = new Counter();
        $form = $this->createForm(CounterFormType::class, $counter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($counter);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le guichet "%s" a été créé avec succès.', $counter->getName()));

            return $this->redirectToRoute('admin_counter_index');
        }

        return $this->render('admin/counter/new.html.twig', [
            'counter' => $counter,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_counter_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Counter $counter, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CounterFormType::class, $counter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $counter->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le guichet "%s" a été mis à jour.', $counter->getName()));

            return $this->redirectToRoute('admin_counter_index');
        }

        return $this->render('admin/counter/edit.html.twig', [
            'counter' => $counter,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_counter_toggle_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleActive(Request $request, Counter $counter, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('toggle-active-counter-' . $counter->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_counter_index');
        }

        $counter->setIsActive(!$counter->isActive());
        $counter->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $status = $counter->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Le guichet "%s" a été %s.', $counter->getName(), $status));

        return $this->redirectToRoute('admin_counter_index');
    }

    #[Route('/{id}/delete', name: 'admin_counter_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Counter $counter, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-counter-' . $counter->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_counter_index');
        }

        $entityManager->remove($counter);
        $entityManager->flush();

        $this->addFlash('success', 'Le guichet a été supprimé.');

        return $this->redirectToRoute('admin_counter_index');
    }
}
