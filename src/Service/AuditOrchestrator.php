<?php

namespace App\Service;

use App\Entity\Audit;
use App\Entity\AuditResult;
use Doctrine\ORM\EntityManagerInterface;

class AuditOrchestrator
{
    /**
     * @param iterable<RiskDataProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
        private RiskScoringService $riskScoringService,
        private RecommendationsEngine $recommendationsEngine,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function compute(Audit $audit): AuditResult
    {
        $riskData = [];
        foreach ($this->providers as $provider) {
            $riskData[] = $provider->fetch($audit->getLat() ?? 0.0, $audit->getLng() ?? 0.0);
        }

        $scoring = $this->riskScoringService->score($riskData, $audit->hasBasement());

        $recommendations = $this->recommendationsEngine->buildTopActions(
            $scoring->scores,
            $audit->getInputActivityType(),
            $audit->getInputBuildingType(),
            $audit->hasBasement(),
            $audit->getInputCriticality()
        );

        $result = $audit->getResult() ?? new AuditResult();
        $result->setAudit($audit);
        $result->setScoresJson([
            ...$scoring->scores,
            'global' => $scoring->globalScore,
        ]);
        $result->setLevelsJson([
            ...$scoring->levels,
            'global' => $scoring->globalLevel,
        ]);
        $result->setConfidenceScore($scoring->confidenceScore);
        $result->setExplanationsJson($scoring->explanations);
        $result->setRecommendationsJson($recommendations);
        $result->setDataSourcesJson($scoring->dataSources);
        $result->setComputedAt(new \DateTimeImmutable());

        $audit->setStatus(Audit::STATUS_COMPUTED);
        $audit->setResult($result);

        $this->entityManager->persist($audit);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $result;
    }
}
