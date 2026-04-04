<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response|RedirectResponse
    {
        $user = $this->getUser();

        // If user is logged in, redirect to appropriate dashboard
        if ($user instanceof User) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_social_index');
        }

        // Not authenticated - redirect to login
        return $this->redirectToRoute('app_login');
    }
}
