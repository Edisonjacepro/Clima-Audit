<?php

namespace App\MessageHandler;

use App\Entity\Audit;
use App\Message\ComputeAuditMessage;
use App\Service\AuditOrchestrator;
use App\Service\DataSourceUnavailableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ComputeAuditHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditOrchestrator $auditOrchestrator
    ) {
    }

    public function __invoke(ComputeAuditMessage $message): void
    {
        $audit = $this->entityManager->getRepository(Audit::class)->find($message->auditId);
        if (!$audit instanceof Audit) {
            return;
        }

        if ($audit->getStatus() === Audit::STATUS_COMPUTED && $audit->getResult() !== null) {
            return;
        }

        try {
            $this->auditOrchestrator->compute($audit);
        } catch (DataSourceUnavailableException $exception) {
            $audit->setStatus(Audit::STATUS_FAILED);
            $this->entityManager->persist($audit);
            $this->entityManager->flush();
        }
    }
}
