<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\ArcGisRiskClient;

class FloodRiskProvider extends AbstractRiskProvider
{
    public function __construct(
        private ArcGisRiskClient $arcGisRiskClient,
        private string $frequentLayerUrl,
        private string $mediumLayerUrl,
        private string $rareLayerUrl,
        \Psr\Cache\CacheItemPoolInterface $cache
    ) {
        parent::__construct($cache);
    }

    public function getHazard(): string
    {
        return 'flood';
    }

    protected function getProviderName(): string
    {
        return 'Georisques - Alea ruissellement (ArcGIS)';
    }

    protected function getProviderVersion(): string
    {
        return 'FeatureServer';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $frequentCount = $this->arcGisRiskClient->fetchCount($this->frequentLayerUrl, $lat, $lng);
        $mediumCount = $this->arcGisRiskClient->fetchCount($this->mediumLayerUrl, $lat, $lng);
        $rareCount = $this->arcGisRiskClient->fetchCount($this->rareLayerUrl, $lat, $lng);

        $score = 10;
        $classLabel = 'aucun';
        if ($frequentCount > 0) {
            $score = 90;
            $classLabel = 'frequent';
        } elseif ($mediumCount > 0) {
            $score = 60;
            $classLabel = 'moyen';
        } elseif ($rareCount > 0) {
            $score = 30;
            $classLabel = 'rare';
        }

        $data = [
            'frequent' => $frequentCount,
            'medium' => $mediumCount,
            'rare' => $rareCount,
            'class' => $classLabel,
        ];

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: $data,
            normalizedScore: $score,
            explanation: sprintf(
                'Alea ruissellement (pluvial) issu des donnees officielles Georisques. Classe detectee: %s.',
                $classLabel
            ),
            confidence: 70,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }
}
