<?php

namespace App\Entity;

use App\Repository\PdfReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PdfReportRepository::class)]
class PdfReport
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_READY = 'READY';
    public const STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'pdfReport')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pathOrKey = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $generatedAt = null;

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
        if ($audit->getPdfReport() !== $this) {
            $audit->setPdfReport($this);
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPathOrKey(): ?string
    {
        return $this->pathOrKey;
    }

    public function setPathOrKey(?string $pathOrKey): self
    {
        $this->pathOrKey = $pathOrKey;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }
}
