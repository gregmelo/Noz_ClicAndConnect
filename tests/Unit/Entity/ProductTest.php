<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testProductAttributes(): void
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setDescription('This is a test product');
        $product->setPrice(19.99);
        $product->setStock(10);
        $product->setImageFilename('test.jpg');

        $this->assertEquals('Test Product', $product->getName());
        $this->assertEquals('This is a test product', $product->getDescription());
        $this->assertEquals(19.99, $product->getPrice());
        $this->assertEquals(10, $product->getStock());
        $this->assertEquals('test.jpg', $product->getImageFilename());
    }
}
