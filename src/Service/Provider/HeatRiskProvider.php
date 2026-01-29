<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;

class HeatRiskProvider extends AbstractRiskProvider
{
    public function getHazard(): string
    {
        return 'heat';
    }

    protected function getProviderName(): string
    {
        return 'MockHeatProvider';
    }

    protected function getProviderVersion(): string
    {
        return 'v1';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $score = $this->scoreFromCoords($lat, $lng, 7);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'heat_index' => $score,
                'lat' => $lat,
                'lng' => $lng,
            ],
            normalizedScore: $score,
            explanation: 'Exposition estimée aux vagues de chaleur sur la base d’un modèle simplifié.',
            confidence: 55,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromCoords(float $lat, float $lng, int $seed): int
    {
        $value = abs(sin(($lat + $lng + $seed) * 0.15));

        return (int) round(min(100, $value * 100));
    }
}
