<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BannedController extends AbstractController
{
    #[Route('/banned', name: 'app_banned')]
    #[IsGranted('ROLE_CLIENT')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user || !$user->getBanExpiresAt() || $user->getBanExpiresAt() < new \DateTimeImmutable()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('banned/index.html.twig', [
            'banExpiresAt' => $user->getBanExpiresAt(),
        ]);
    }
}
