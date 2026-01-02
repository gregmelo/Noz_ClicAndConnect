<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityLog Entity
 * 
 * Records system-wide activities (e.g., product creation, reservation status changes, logins).
 * Used for auditing and monitoring administrative and user actions.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    /** @var int|null The unique identifier of the log entry */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var string|null The email of the user who performed the action */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userEmail = null;

    /** @var string|null The technical name of the action performed (e.g., 'PRODUCT_CREATED') */
    #[ORM\Column(length: 255)]
    private ?string $action = null;

    /** @var array Additional data related to the action (old values, new values, references, etc.) */
    #[ORM\Column]
    private array $context = [];

    /** @var \DateTimeImmutable|null Date and time when the action occurred */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

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
}
