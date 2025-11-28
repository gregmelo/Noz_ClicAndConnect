<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/add/{id}', name: 'app_cart_add')]
    public function add(int $id, Request $request): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);
        $this->cartService->add($id, $quantity);

        $this->addFlash('success', 'Produit ajouté au panier.');

        // Redirect to cart if requested, otherwise back to product list
        if ($request->query->get('returnToCart')) {
            return $this->redirectToRoute('app_cart_index');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove')]
    public function remove(int $id): Response
    {
        $this->cartService->remove($id);
        $this->addFlash('success', 'Produit retiré du panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/decrease/{id}', name: 'app_cart_decrease')]
    public function decrease(int $id): Response
    {
        $this->cartService->decrease($id);

        return $this->redirectToRoute('app_cart_index');
    }
}
