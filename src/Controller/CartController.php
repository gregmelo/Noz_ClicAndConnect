<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CartController
 * 
 * Manages the user's shopping cart stored in the session.
 */
#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    #[Route('/', name: 'app_cart_index')]
    public function index(): Response
    {
        return $this->render('cart/index.html.twig', [
            'cart' => $this->cartService->getFullCart(),
            'total' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Add a product to the cart
     *
     * @param int $id Product ID
     * @param Request $request
     * @return Response
     */
    #[Route('/add/{id}', name: 'app_cart_add')]
    public function add(int $id, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('add_to_cart', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_home');
            }
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user && $user->getBanExpiresAt() && $user->getBanExpiresAt() > new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Votre compte est suspendu. Vous ne pouvez pas ajouter d\'articles.');
            return $this->redirectToRoute('app_banned');
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $this->cartService->add($id, $quantity);

        $this->addFlash('success', 'Produit ajouté au panier.');

        // Redirect to cart if requested, otherwise back to product list
        if ($request->query->get('returnToCart')) {
            return $this->redirectToRoute('app_cart_index');
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * Remove a product completely from the cart
     *
     * @param int $id Product ID
     * @return Response
     */
    #[Route('/remove/{id}', name: 'app_cart_remove')]
    public function remove(int $id): Response
    {
        $this->cartService->remove($id);
        $this->addFlash('success', 'Produit retiré du panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    /**
     * Decrease the quantity of a product in the cart
     *
     * @param int $id Product ID
     * @return Response
     */
    #[Route('/decrease/{id}', name: 'app_cart_decrease')]
    public function decrease(int $id): Response
    {
        $this->cartService->decrease($id);

        return $this->redirectToRoute('app_cart_index');
    }

    /**
     * Complete clear the shopping cart
     *
     * @return Response
     */
    #[Route('/clear', name: 'app_cart_clear')]
    public function clear(): Response
    {
        $this->cartService->clear();
        $this->addFlash('success', 'Votre panier a été vidé.');

        return $this->redirectToRoute('app_cart_index');
    }
}
