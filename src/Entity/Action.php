<?php

namespace App\Entity;

use App\Repository\ActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActionRepository::class)]
class Action
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::JSON)]
    private array $hazardTags = [];

    #[ORM\Column(type: Types::JSON)]
    private array $sectorTags = [];

    #[ORM\Column(length: 10)]
    private string $effort = 'low';

    #[ORM\Column(length: 10)]
    private string $cost = '€';

    #[ORM\Column(length: 10)]
    private string $impact = 'low';

    #[ORM\Column(length: 10)]
    private string $horizon = 'now';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequisites = null;

    #[ORM\Column]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getHazardTags(): array
    {
        return $this->hazardTags;
    }

    public function setHazardTags(array $hazardTags): self
    {
        $this->hazardTags = $hazardTags;

        return $this;
    }

    public function getSectorTags(): array
    {
        return $this->sectorTags;
    }

    public function setSectorTags(array $sectorTags): self
    {
        $this->sectorTags = $sectorTags;

        return $this;
    }

    public function getEffort(): string
    {
        return $this->effort;
    }

    public function setEffort(string $effort): self
    {
        $this->effort = $effort;

        return $this;
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function setCost(string $cost): self
    {
        $this->cost = $cost;

        return $this;
    }

    public function getImpact(): string
    {
        return $this->impact;
    }

    public function setImpact(string $impact): self
    {
        $this->impact = $impact;

        return $this;
    }

    public function getHorizon(): string
    {
        return $this->horizon;
    }

    public function setHorizon(string $horizon): self
    {
        $this->horizon = $horizon;

        return $this;
    }

    public function getPrerequisites(): ?string
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(?string $prerequisites): self
    {
        $this->prerequisites = $prerequisites;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
