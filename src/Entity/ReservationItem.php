<?php

namespace App\Entity;

use App\Repository\ReservationItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ReservationItem Entity
 * 
 * Represents a single product entry within a reservation, 
 * capturing the quantity and the price at the time of reservation.
 */
#[ORM\Entity(repositoryClass: ReservationItemRepository::class)]
class ReservationItem
{
    /** @var int|null The unique identifier of the reservation item */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Reservation|null The parent reservation */
    #[ORM\ManyToOne(inversedBy: 'reservationItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    /** @var Product|null The product being reserved */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    /** @var int|null The quantity of the product reserved */
    #[ORM\Column]
    private ?int $quantity = null;

    /** @var string|null The unit price of the product at the moment of reservation */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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
}
