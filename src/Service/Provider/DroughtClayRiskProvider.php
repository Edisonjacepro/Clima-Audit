<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\ArcGisRiskClient;

class DroughtClayRiskProvider extends AbstractRiskProvider
{
    public function __construct(
        private ArcGisRiskClient $arcGisRiskClient,
        private string $layerUrl,
        \Psr\Cache\CacheItemPoolInterface $cache
    ) {
        parent::__construct($cache);
    }

    public function getHazard(): string
    {
        return 'drought_clay';
    }

    protected function getProviderName(): string
    {
        return 'Georisques - RGA (ArcGIS)';
    }

    protected function getProviderVersion(): string
    {
        return 'FeatureServer';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $attributes = $this->arcGisRiskClient->fetchFirstAttributes($this->layerUrl, $lat, $lng);

        if ($attributes === null) {
            return new RiskDataDTO(
                hazard: $this->getHazard(),
                rawIndicators: ['status' => 'no_data'],
                normalizedScore: 0,
                explanation: 'Donnee RGA indisponible ou zone non couverte.',
                confidence: 0,
                sourceMeta: $this->buildSourceMeta($lat, $lng)
            );
        }

        $score = $this->scoreFromAttributes($attributes);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: ['attributes' => $attributes],
            normalizedScore: $score,
            explanation: 'Sensibilite au retrait-gonflement des argiles issue des donnees officielles Georisques.',
            confidence: 70,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function scoreFromAttributes(array $attributes): int
    {
        $value = null;
        foreach (['ALEA', 'NIVEAU', 'NIV'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $value = $attributes[$key];
                break;
            }
        }

        if (is_numeric($value)) {
            $numeric = (int) $value;
            if ($numeric <= 1) {
                return 25;
            }
            if ($numeric === 2) {
                return 55;
            }
            if ($numeric >= 3) {
                return 80;
            }
        }

        $label = '';
        if (is_string($value)) {
            $label = strtolower($value);
        } else {
            foreach ($attributes as $attrValue) {
                if (is_string($attrValue)) {
                    $label = strtolower($attrValue);
                    break;
                }
            }
        }

        if (str_contains($label, 'tres') && str_contains($label, 'fort')) {
            return 90;
        }
        if (str_contains($label, 'fort')) {
            return 80;
        }
        if (str_contains($label, 'moyen')) {
            return 55;
        }
        if (str_contains($label, 'faible')) {
            return 25;
        }
        if (str_contains($label, 'nul') || str_contains($label, 'aucun')) {
            return 10;
        }

        return 10;
    }
}
