<?php

namespace App\Entity;

use App\Repository\GlobalStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GlobalStatRepository::class)]
class GlobalStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $totalRevenue = 0.0;

    #[ORM\Column]
    private ?int $totalCollectedCount = 0;

    #[ORM\Column]
    private ?int $totalExpiredCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTotalRevenue(): ?float
    {
        return $this->totalRevenue;
    }

    public function setTotalRevenue(float $totalRevenue): static
    {
        $this->totalRevenue = $totalRevenue;

        return $this;
    }

    public function getTotalCollectedCount(): ?int
    {
        return $this->totalCollectedCount;
    }

    public function setTotalCollectedCount(int $totalCollectedCount): static
    {
        $this->totalCollectedCount = $totalCollectedCount;

        return $this;
    }

    public function getTotalExpiredCount(): ?int
    {
        return $this->totalExpiredCount;
    }

    public function setTotalExpiredCount(int $totalExpiredCount): static
    {
        $this->totalExpiredCount = $totalExpiredCount;

        return $this;
    }
}
