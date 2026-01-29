<?php

namespace App\Entity;

use App\Repository\AuditResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditResultRepository::class)]
class AuditResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'result')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\Column(type: Types::JSON)]
    private array $scoresJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $levelsJson = [];

    #[ORM\Column]
    private int $confidenceScore = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $explanationsJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $recommendationsJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $dataSourcesJson = [];

    #[ORM\Column]
    private \DateTimeImmutable $computedAt;

    public function __construct()
    {
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAudit(): Audit
    {
        return $this->audit;
    }

    public function setAudit(Audit $audit): self
    {
        $this->audit = $audit;
        if ($audit->getResult() !== $this) {
            $audit->setResult($this);
        }

        return $this;
    }

    public function getScoresJson(): array
    {
        return $this->scoresJson;
    }

    public function setScoresJson(array $scoresJson): self
    {
        $this->scoresJson = $scoresJson;

        return $this;
    }

    public function getLevelsJson(): array
    {
        return $this->levelsJson;
    }

    public function setLevelsJson(array $levelsJson): self
    {
        $this->levelsJson = $levelsJson;

        return $this;
    }

    public function getConfidenceScore(): int
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(int $confidenceScore): self
    {
        $this->confidenceScore = $confidenceScore;

        return $this;
    }

    public function getExplanationsJson(): array
    {
        return $this->explanationsJson;
    }

    public function setExplanationsJson(array $explanationsJson): self
    {
        $this->explanationsJson = $explanationsJson;

        return $this;
    }

    public function getRecommendationsJson(): array
    {
        return $this->recommendationsJson;
    }

    public function setRecommendationsJson(array $recommendationsJson): self
    {
        $this->recommendationsJson = $recommendationsJson;

        return $this;
    }

    public function getDataSourcesJson(): array
    {
        return $this->dataSourcesJson;
    }

    public function setDataSourcesJson(array $dataSourcesJson): self
    {
        $this->dataSourcesJson = $dataSourcesJson;

        return $this;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function setComputedAt(\DateTimeImmutable $computedAt): self
    {
        $this->computedAt = $computedAt;

        return $this;
    }
}
