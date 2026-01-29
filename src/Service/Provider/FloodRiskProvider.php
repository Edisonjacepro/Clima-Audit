<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;

class FloodRiskProvider extends AbstractRiskProvider
{
    public function getHazard(): string
    {
        return 'flood';
    }

    protected function getProviderName(): string
    {
        return 'MockFloodProvider';
    }

    protected function getProviderVersion(): string
    {
        return 'v1';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $score = $this->scoreFromCoords($lat, $lng, 13);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'flood_index' => $score,
                'lat' => $lat,
                'lng' => $lng,
            ],
            normalizedScore: $score,
            explanation: 'Risque d’inondation estimé via un proxy topographique simplifié.',
            confidence: 60,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromCoords(float $lat, float $lng, int $seed): int
    {
        $value = abs(cos(($lat * 0.8 + $lng + $seed) * 0.12));

        return (int) round(min(100, $value * 100));
    }
}
