<?php

namespace App\Entity;

use App\Repository\AdminConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminConfigRepository::class)]
class AdminConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    private array $hazardWeightsJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $thresholdsJson = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHazardWeightsJson(): array
    {
        return $this->hazardWeightsJson;
    }

    public function setHazardWeightsJson(array $hazardWeightsJson): self
    {
        $this->hazardWeightsJson = $hazardWeightsJson;

        return $this;
    }

    public function getThresholdsJson(): array
    {
        return $this->thresholdsJson;
    }

    public function setThresholdsJson(array $thresholdsJson): self
    {
        $this->thresholdsJson = $thresholdsJson;

        return $this;
    }
}
