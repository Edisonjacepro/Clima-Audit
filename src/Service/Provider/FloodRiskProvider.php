<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\ArcGisRiskClient;
use App\Service\DataSourceUnavailableException;
use App\Service\GeoApiGouvService;
use App\Service\HubEauHydrometryClient;

class FloodRiskProvider extends AbstractRiskProvider
{
    public function __construct(
        private ArcGisRiskClient $arcGisRiskClient,
        private GeoApiGouvService $geoApiGouvService,
        private HubEauHydrometryClient $hubEauHydrometryClient,
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
        return 'Georisques (Alea ruissellement) + Hub\'Eau (Hydrometrie)';
    }

    protected function getProviderVersion(): string
    {
        return 'FeatureServer + v2';
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

        $hydrometry = null;
        $hydrometryError = null;
        try {
            $hydrometry = $this->buildHydrometryContext($lat, $lng);
        } catch (DataSourceUnavailableException $exception) {
            $hydrometryError = $exception->getMessage();
        }

        if ($hydrometry !== null) {
            $data['hydrometry'] = $hydrometry;
        } elseif ($hydrometryError !== null) {
            $data['hydrometry_error'] = $hydrometryError;
        }

        $explanation = sprintf(
            'Alea ruissellement (pluvial) issu des donnees officielles Georisques. Classe detectee: %s.',
            $classLabel
        );
        if ($hydrometry !== null) {
            $explanation .= sprintf(
                ' Hydrometrie Hub\'Eau: station %s, hauteur %.3fm (date %s).',
                $hydrometry['station']['name'] ?? 'inconnue',
                $hydrometry['observation']['height_m'] ?? 0.0,
                $hydrometry['observation']['date'] ?? 'n/a'
            );
        } elseif ($hydrometryError !== null) {
            $explanation .= ' Hydrometrie Hub\'Eau indisponible.';
        }

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: $data,
            normalizedScore: $score,
            explanation: $explanation,
            confidence: $hydrometry !== null ? 75 : 70,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function buildHydrometryContext(float $lat, float $lng): ?array
    {
        $commune = $this->geoApiGouvService->getCommune($lat, $lng);
        $station = $this->hubEauHydrometryClient->fetchNearestStation($lat, $lng, $commune->code);

        if ($station === null || !isset($station['code_station'])) {
            return null;
        }

        $observation = $this->hubEauHydrometryClient->fetchLatestObservation((string) $station['code_station']);
        if ($observation === null) {
            return null;
        }

        return [
            'commune_insee' => $commune->code,
            'department' => $commune->departmentCode,
            'station' => [
                'code' => (string) ($station['code_station'] ?? ''),
                'name' => (string) ($station['libelle_station'] ?? ''),
                'distance_km' => $station['distance_km'] ?? null,
            ],
            'observation' => [
                'date' => (string) ($observation['date_obs'] ?? ''),
                'resultat_obs_mm' => $observation['resultat_obs'] ?? null,
                'height_m' => $observation['height_m'] ?? null,
            ],
        ];
    }
}
