<?php

namespace App\Controller;

use App\Entity\User;
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
class UserAdminController extends AbstractController
{
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

    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($request->isMethod('POST')) {
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

    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$this->isGranted('USER_EDIT', $user)) {
            $this->addFlash('danger', 'Vous n\'avez pas les permissions pour modifier cet utilisateur.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        if ($request->isMethod('POST')) {
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
                $user->setRoles([$role]);

                $entityManager->flush();

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

    #[Route('/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('USER_DELETE', $user)) {
            $this->addFlash('danger', 'Vous n\'avez pas les permissions pour supprimer cet utilisateur.');
            return $this->redirectToRoute('app_admin_users_index');
        }

        $entityManager->remove($user);
        $entityManager->flush();

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
