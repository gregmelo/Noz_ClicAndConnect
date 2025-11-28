<?php

namespace App\Service;

use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    public function __construct(
        private RequestStack $requestStack,
        private ProductRepository $productRepository
    ) {
    }

    public function add(int $id, int $quantity = 1): void
    {
        $cart = $this->getSession()->get('cart', []);

        if (!empty($cart[$id])) {
            $cart[$id] += $quantity;
        } else {
            $cart[$id] = $quantity;
        }

        $this->getSession()->set('cart', $cart);
    }

    public function remove(int $id): void
    {
        $cart = $this->getSession()->get('cart', []);

        if (!empty($cart[$id])) {
            unset($cart[$id]);
        }

        $this->getSession()->set('cart', $cart);
    }

    public function decrease(int $id): void
    {
        $cart = $this->getSession()->get('cart', []);

        if (!empty($cart[$id])) {
            if ($cart[$id] > 1) {
                $cart[$id]--;
            } else {
                unset($cart[$id]);
            }
        }

        $this->getSession()->set('cart', $cart);
    }

    public function getFullCart(): array
    {
        $cart = $this->getSession()->get('cart', []);
        $cartData = [];

        foreach ($cart as $id => $quantity) {
            $product = $this->productRepository->find($id);
            if ($product) {
                $cartData[] = [
                    'product' => $product,
                    'quantity' => $quantity
                ];
            } else {
                // Product no longer exists, remove from cart
                $this->remove($id);
            }
        }

        return $cartData;
    }

    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->getFullCart() as $item) {
            $total += $item['product']->getPrice() * $item['quantity'];
        }

        return $total;
    }

    public function clear(): void
    {
        $this->getSession()->remove('cart');
    }

    public function getTotalQuantity(): int
    {
        $cart = $this->getSession()->get('cart', []);
        $totalQuantity = 0;

        foreach ($cart as $quantity) {
            $totalQuantity += $quantity;
        }

        return $totalQuantity;
    }

    private function getSession()
    {
        return $this->requestStack->getSession();
    }
}
