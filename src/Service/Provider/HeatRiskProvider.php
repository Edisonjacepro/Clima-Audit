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
        return 'MeteoFrance (indisponible)';
    }

    protected function getProviderVersion(): string
    {
        return 'n/a';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'status' => 'unavailable',
            ],
            normalizedScore: 0,
            explanation: 'Donnee officielle chaleur indisponible (aucune API publique stable connectee).',
            confidence: 0,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }
}
