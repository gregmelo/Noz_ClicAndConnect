<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Product Entity
 * 
 * Represents a product available for reservation in the Clic & Collect system.
 * Includes stock management, pricing (current and original), and image handling.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    /** @var int|null The unique identifier of the product */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var string|null The name of the product */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** @var string|null A detailed description of the product */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /** @var string|null The current price of the product (decimal string) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    /** @var int|null The current quantity in stock */
    #[ORM\Column]
    private ?int $stock = null;

    /** @var string|null The filename of the product's image */
    #[ORM\Column(length: 255)]
    private ?string $imageFilename = null;

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null Virtual property for image upload */
    private ?\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile = null;

    /** @var \DateTimeImmutable|null Date and time when the product was added */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var \DateTimeImmutable|null Date and time of the last update */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var string|null The original price before any discount (decimal string) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $originalPrice = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getImageFile(): ?\Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->imageFile;
    }

    public function setImageFile(?\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile): static
    {
        $this->imageFile = $imageFile;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getOriginalPrice(): ?string
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(?string $originalPrice): static
    {
        $this->originalPrice = $originalPrice;

        return $this;
    }
    /** @var User|null The user (employee/admin) who created this product */
    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?User $createdBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
    /** @var Category|null The category this product belongs to */
    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Category $category = null;

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
