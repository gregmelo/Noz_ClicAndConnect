<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_CLIENT')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userRank = null;

        // Check Ranking if Employee/Admin/Dev
        if ($this->isGranted('ROLE_EMPLOYEE')) {
            $bestSellers = $entityManager->getRepository(\App\Entity\ReservationItem::class)->createQueryBuilder('ri')
                ->select('u.id', 'SUM(ri.quantity * p.price) as revenue')
                ->join('ri.product', 'p')
                ->join('p.createdBy', 'u')
                ->join('ri.reservation', 'r')
                ->where('r.status = :status')
                ->setParameter('status', 'COLLECTED')
                ->groupBy('u.id')
                ->orderBy('revenue', 'DESC')
                ->setMaxResults(3) // Only care about top 3
                ->getQuery()
                ->getResult();

            foreach ($bestSellers as $index => $seller) {
                if ($seller['id'] === $user->getId()) {
                    $userRank = $index + 1; // 1, 2, or 3
                    break;
                }
            }
        }

        return $this->render('profile/index.html.twig', [
            'userRank' => $userRank,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_edit', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_profile_edit');
            }
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $email = $request->request->get('email');

            if ($firstName && $lastName && $email) {
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setEmail($email);

                $entityManager->flush();

                $this->addFlash('success', 'Profil mis à jour avec succès !');
                return $this->redirectToRoute('app_profile');
            } else {
                $this->addFlash('danger', 'Tous les champs sont requis.');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('change_password', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_profile_change_password');
            }
            $currentPassword = $request->request->get('currentPassword');
            $newPassword = $request->request->get('newPassword');
            $confirmPassword = $request->request->get('confirmPassword');

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            } elseif (strlen($newPassword) < 6) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 6 caractères.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe modifié avec succès !');
                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/change_password.html.twig');
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, \Symfony\Bundle\SecurityBundle\Security $security): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            // Manually remove reservations to ensure clean deletion
            $reservations = $entityManager->getRepository(\App\Entity\Reservation::class)->findBy(['user' => $user]);
            foreach ($reservations as $reservation) {
                $entityManager->remove($reservation);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $security->logout(false);

            $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('danger', 'Token de sécurité invalide.');
        return $this->redirectToRoute('app_profile');
    }
}
