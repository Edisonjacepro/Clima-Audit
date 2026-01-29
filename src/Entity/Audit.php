<?php

namespace App\Entity;

use App\Repository\AuditRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Audit
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STATUS_PAID = 'PAID';
    public const STATUS_COMPUTED = 'COMPUTED';
    public const STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'audits')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Merci de saisir une adresse complete.')]
    #[Assert\Length(min: 8, minMessage: 'Adresse trop courte, merci de preciser davantage.')]
    private string $address = '';

    #[ORM\Column(nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(nullable: true)]
    private ?float $lng = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $inputActivityType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $inputBuildingType = null;

    #[ORM\Column]
    private bool $inputHasBasement = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $inputCriticality = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'audit', targetEntity: AuditResult::class, cascade: ['persist', 'remove'])]
    private ?AuditResult $result = null;

    #[ORM\OneToOne(mappedBy: 'audit', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    private ?Payment $payment = null;

    #[ORM\OneToOne(mappedBy: 'audit', targetEntity: PdfReport::class, cascade: ['persist', 'remove'])]
    private ?PdfReport $pdfReport = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(?float $lng): self
    {
        $this->lng = $lng;

        return $this;
    }

    public function getInputActivityType(): ?string
    {
        return $this->inputActivityType;
    }

    public function setInputActivityType(?string $inputActivityType): self
    {
        $this->inputActivityType = $inputActivityType;

        return $this;
    }

    public function getInputBuildingType(): ?string
    {
        return $this->inputBuildingType;
    }

    public function setInputBuildingType(?string $inputBuildingType): self
    {
        $this->inputBuildingType = $inputBuildingType;

        return $this;
    }

    public function hasBasement(): bool
    {
        return $this->inputHasBasement;
    }

    public function getInputHasBasement(): bool
    {
        return $this->inputHasBasement;
    }

    public function isInputHasBasement(): bool
    {
        return $this->inputHasBasement;
    }

    public function setInputHasBasement(bool $inputHasBasement): self
    {
        $this->inputHasBasement = $inputHasBasement;

        return $this;
    }

    public function getInputCriticality(): ?string
    {
        return $this->inputCriticality;
    }

    public function setInputCriticality(?string $inputCriticality): self
    {
        $this->inputCriticality = $inputCriticality;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getResult(): ?AuditResult
    {
        return $this->result;
    }

    public function setResult(?AuditResult $result): self
    {
        $this->result = $result;
        if ($result !== null && $result->getAudit() !== $this) {
            $result->setAudit($this);
        }

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        if ($payment !== null && $payment->getAudit() !== $this) {
            $payment->setAudit($this);
        }

        return $this;
    }

    public function getPdfReport(): ?PdfReport
    {
        return $this->pdfReport;
    }

    public function setPdfReport(?PdfReport $pdfReport): self
    {
        $this->pdfReport = $pdfReport;
        if ($pdfReport !== null && $pdfReport->getAudit() !== $this) {
            $pdfReport->setAudit($this);
        }

        return $this;
    }
}
