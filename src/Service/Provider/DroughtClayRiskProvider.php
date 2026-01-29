<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;

class DroughtClayRiskProvider extends AbstractRiskProvider
{
    public function getHazard(): string
    {
        return 'drought_clay';
    }

    protected function getProviderName(): string
    {
        return 'MockDroughtClayProvider';
    }

    protected function getProviderVersion(): string
    {
        return 'v1';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $score = $this->scoreFromCoords($lat, $lng, 21);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'clay_shrink_swell' => $score,
                'lat' => $lat,
                'lng' => $lng,
            ],
            normalizedScore: $score,
            explanation: 'Sensibilité estimée au retrait-gonflement des argiles selon un modèle simplifié.',
            confidence: 45,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromCoords(float $lat, float $lng, int $seed): int
    {
        $value = abs(sin(($lat - $lng + $seed) * 0.18));

        return (int) round(min(100, $value * 100));
    }
}
