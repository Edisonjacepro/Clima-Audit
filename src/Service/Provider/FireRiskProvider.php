<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;

class FireRiskProvider extends AbstractRiskProvider
{
    public function getHazard(): string
    {
        return 'fire';
    }

    protected function getProviderName(): string
    {
        return 'MockFireProvider';
    }

    protected function getProviderVersion(): string
    {
        return 'v1';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $score = $this->scoreFromCoords($lat, $lng, 31);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'fire_risk' => $score,
                'lat' => $lat,
                'lng' => $lng,
            ],
            normalizedScore: $score,
            explanation: 'Potentiel de feux de végétation estimé via un indicateur simplifié.',
            confidence: 50,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromCoords(float $lat, float $lng, int $seed): int
    {
        $value = abs(cos(($lat * 1.1 - $lng + $seed) * 0.14));

        return (int) round(min(100, $value * 100));
    }
}
