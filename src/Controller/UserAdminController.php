<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * UserAdminController
 * 
 * Administrative controller for managing users.
 * Allows creating, editing, and deleting users with role-based restrictions.
 * Accessible only to users with ROLE_ADMIN or higher.
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    /**
     * List users with search and filtering
     *
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    #[Route('/', name: 'app_admin_users_index')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $query = $request->query->get('q');
        $role = $request->query->get('role');

        if ($query || $role) {
            $allUsers = $userRepository->search($query, $role);
        } else {
            $allUsers = $userRepository->findAll();
        }

        $totalUsers = count($allUsers);
        $totalPages = ceil($totalUsers / $limit);
        $users = array_slice($allUsers, $offset, $limit);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'currentQuery' => $query,
            'currentRole' => $role,
        ]);
    }

    /**
     * Create a new user (Admin only)
     *
     * @param Request $request
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EntityManagerInterface $entityManager
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_new', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            $email = $request->request->get('email');
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $password = $request->request->get('password');
            $role = $request->request->get('role');

            // Role restrictions
            $allowedRoles = $this->getAllowedRolesToCreate($currentUser);
            if (!in_array($role, $allowedRoles)) {
                $this->addFlash('danger', 'Vous n\'avez pas les permissions pour créer ce type de compte.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            if ($email && $firstName && $lastName && $password && $role) {
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setRoles([$role]);

                $entityManager->persist($user);
                $entityManager->flush();

                $logger->logUserAction($currentUser, 'USER_ADMIN_CREATED', [
                    'target_user' => $user->getEmail(),
                    'role' => $role
                ]);

                $this->addFlash('success', 'Utilisateur créé avec succès !');
                return $this->redirectToRoute('app_admin_users_index');
            } else {
                $this->addFlash('danger', 'Tous les champs sont requis.');
            }
        }

        return $this->render('admin/users/new.html.twig', [
            'allowedRoles' => $this->getAllowedRolesToCreate($currentUser),
        ]);
    }

    /**
     * Edit an existing user (Admin only)
     * Includes strike management and ban logic.
     *
     * @param User $user
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$this->isGranted('USER_EDIT', $user)) {
            $this->addFlash('danger', 'Vous n\'avez pas les permissions pour modifier cet utilisateur.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_edit', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            $email = $request->request->get('email');
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $role = $request->request->get('role');

            // Role restrictions for the NEW role
            $allowedRoles = $this->getAllowedRolesToCreate($currentUser);
            if (!in_array($role, $allowedRoles)) {
                $this->addFlash('danger', 'Vous n\'avez pas les permissions pour assigner ce rôle.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            if ($email && $firstName && $lastName && $role) {
                $user->setEmail($email);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setLastName($lastName);
                $user->setRoles([$role]);

                // Handle Strikes
                $strikes = (int) $request->request->get('strikes');
                $user->setStrikes($strikes);

                // Auto-ban/Unban logic
                if ($strikes >= 3) {
                    if (!$user->getBanExpiresAt() || $user->getBanExpiresAt() < new \DateTimeImmutable()) {
                        $user->setBanExpiresAt((new \DateTimeImmutable())->modify('+7 days'));
                    }
                } else {
                    $user->setBanExpiresAt(null);
                }

                $entityManager->flush();

                $logger->logUserAction($currentUser, 'USER_ADMIN_UPDATED', [
                    'target_user' => $user->getEmail(),
                    'role' => $role,
                    'strikes' => $strikes
                ]);

                $this->addFlash('success', 'Utilisateur modifié avec succès !');
                return $this->redirectToRoute('app_admin_users_index');
            } else {
                $this->addFlash('danger', 'Tous les champs sont requis.');
            }
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'allowedRoles' => $this->getAllowedRolesToCreate($currentUser),
        ]);
    }

    /**
     * Delete a user (Admin only)
     *
     * @param User $user
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('delete_user'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        if (!$this->isGranted('USER_DELETE', $user)) {
            $this->addFlash('danger', 'Vous n\'avez pas les permissions pour supprimer cet utilisateur.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        $targetEmail = $user->getEmail();
        $entityManager->remove($user);
        $entityManager->flush();

        $logger->logUserAction($this->getUser(), 'USER_ADMIN_DELETED', [
            'target_user' => $targetEmail
        ]);

        $this->addFlash('success', 'Utilisateur supprimé.');
        return $this->redirectToRoute('app_admin_users_index');
    }

    private function getAllowedRolesToCreate(User $user): array
    {
        if ($this->isGranted('ROLE_DEVELOPER')) {
            return ['ROLE_CLIENT', 'ROLE_EMPLOYEE', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_DEVELOPER'];
        } elseif ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return ['ROLE_CLIENT', 'ROLE_EMPLOYEE', 'ROLE_ADMIN'];
        } elseif ($this->isGranted('ROLE_ADMIN')) {
            return ['ROLE_CLIENT', 'ROLE_EMPLOYEE'];
        }

        return [];
    }
}
