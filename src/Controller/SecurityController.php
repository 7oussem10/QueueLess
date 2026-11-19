<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($this->isGranted('ROLE_AGENT')) {
                return $this->redirectToRoute('agent_dashboard');
            }
            return $this->redirectToRoute('client_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $errorMessage = null;
        if ($error) {
            $errorMessage = $error->getMessageKey();
            if ($errorMessage === 'Invalid credentials.') {
                $errorMessage = 'Identifiants invalides. Veuillez vérifier votre adresse email et votre mot de passe.';
            } else {
                $errorMessage = $error->getMessage();
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'errorMessage' => $errorMessage,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode sera interceptée par la clé logout du pare-feu de sécurité.');
    }
}
