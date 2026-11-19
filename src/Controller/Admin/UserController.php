<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserEditFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = $request->query->get('q');
        $roleFilter = $request->query->get('role');

        $users = $userRepository->searchUsers($search, $roleFilter);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $roleFilter,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $currentUser = $this->getUser();
        $form = $this->createForm(UserEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Business rule: prevent current admin from removing their own admin role or deactivating their own account
            if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
                $roles = $form->get('roles')->getData();
                if (!in_array('ROLE_ADMIN', $roles, true)) {
                    $this->addFlash('danger', 'Sécurité : Vous ne pouvez pas révoquer votre propre rôle Administrateur.');
                    return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
                }
                if (!$user->isActive()) {
                    $this->addFlash('danger', 'Sécurité : Vous ne pouvez pas désactiver votre propre compte.');
                    return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
                }
            }

            // Optional password update
            $newPassword = $form->get('newPassword')->getData();
            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', sprintf('L\'utilisateur "%s" a été mis à jour avec succès.', $user->getFullName()));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_user_toggle_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleActive(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-active-' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité CSRF invalide.');
            return $this->redirectToRoute('admin_user_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('danger', 'Sécurité : Vous ne pouvez pas désactiver votre propre compte administrateur.');
            return $this->redirectToRoute('admin_user_index');
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $status = $user->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Le compte de %s a été %s.', $user->getFullName(), $status));

        return $this->redirectToRoute('admin_user_index');
    }
}
