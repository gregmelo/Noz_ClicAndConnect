<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reservation Entity
 * 
 * Represents a click & collect reservation made by a user.
 * Manages the lifecycle of a reservation from ACTIVE to COLLECTED or CANCELLED,
 * including expiration logic and reference generation.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    /** @var int|null The unique identifier of the reservation */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var User|null The user who made the reservation */
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** @var string|null Unique human-readable reference for the reservation */
    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    /** @var string|null Optional comment from the user */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    /**
     * @var Collection<int, ReservationItem>
     */
    /** @var Collection<int, ReservationItem> List of items included in this reservation */
    #[ORM\OneToMany(targetEntity: ReservationItem::class, mappedBy: 'reservation', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $reservationItems;

    /** @var \DateTimeImmutable|null Date and time when the reservation was created */
    #[ORM\Column]
    private ?\DateTimeImmutable $reservedAt = null;

    /** @var \DateTimeImmutable|null Expiration date and time of the reservation */
    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    /** @var int|null Duration in hours before the reservation expires (default 48) */
    #[ORM\Column]
    private ?int $durationHours = 48;

    /** @var string|null Status of the reservation (ACTIVE, READY, COLLECTED, CANCELLED, EXPIRED) */
    #[ORM\Column(length: 20)]
    private ?string $status = 'ACTIVE';

    public function __construct()
    {
        $this->reservedAt = new \DateTimeImmutable();
        $this->status = 'ACTIVE';
        $this->reservationItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(\DateTimeImmutable $reservedAt): static
    {
        $this->reservedAt = $reservedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDurationHours(): ?int
    {
        return $this->durationHours;
    }

    public function setDurationHours(int $durationHours): static
    {
        $this->durationHours = $durationHours;

        return $this;
    }
    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<int, ReservationItem>
     */
    public function getReservationItems(): Collection
    {
        return $this->reservationItems;
    }

    public function addReservationItem(ReservationItem $reservationItem): static
    {
        if (!$this->reservationItems->contains($reservationItem)) {
            $this->reservationItems->add($reservationItem);
            $reservationItem->setReservation($this);
        }

        return $this;
    }

    public function removeReservationItem(ReservationItem $reservationItem): static
    {
        if ($this->reservationItems->removeElement($reservationItem)) {
            // set the owning side to null (unless already changed)
            if ($reservationItem->getReservation() === $this) {
                $reservationItem->setReservation(null);
            }
        }

        return $this;
    }

    /**
     * Checks if the reservation has passed its expiration date.
     * 
     * @return bool True if expired, false otherwise.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Returns the current effective status, taking into account time-based expiration.
     * If the status is ACTIVE or READY but the expiration date has passed, returns 'EXPIRED'.
     * 
     * @return string The effective status of the reservation.
     */
    public function getEffectiveStatus(): string
    {
        if (in_array($this->status, ['ACTIVE', 'READY']) && $this->isExpired()) {
            return 'EXPIRED';
        }
        return $this->status ?? 'ACTIVE';
    }
}
