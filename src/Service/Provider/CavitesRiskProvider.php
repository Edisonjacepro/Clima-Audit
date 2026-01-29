<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\ArcGisRiskClient;

class CavitesRiskProvider extends AbstractRiskProvider
{
    private const DEFAULT_RADIUS_METERS = 500;

    public function __construct(
        private ArcGisRiskClient $arcGisRiskClient,
        private string $layerUrl,
        \Psr\Cache\CacheItemPoolInterface $cache,
        private int $radiusMeters = self::DEFAULT_RADIUS_METERS
    ) {
        parent::__construct($cache);
    }

    public function getHazard(): string
    {
        return 'cavites';
    }

    protected function getProviderName(): string
    {
        return 'Georisques - BDCavites (ArcGIS)';
    }

    protected function getProviderVersion(): string
    {
        return 'FeatureServer';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $count = $this->arcGisRiskClient->fetchCount($this->layerUrl, $lat, $lng, $this->radiusMeters);
        $score = $this->scoreFromCount($count);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'count' => $count,
                'radius_m' => $this->radiusMeters,
            ],
            normalizedScore: $score,
            explanation: sprintf(
                'Inventaire BDCavites Georisques. %d cavite(s) recensee(s) dans un rayon de %dm.',
                $count,
                $this->radiusMeters
            ),
            confidence: 65,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromCount(int $count): int
    {
        if ($count <= 0) {
            return 10;
        }
        if ($count === 1) {
            return 35;
        }
        if ($count <= 4) {
            return 60;
        }

        return 85;
    }
}
