<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\DataSourceUnavailableException;
use App\Service\GeoApiGouvService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HeatRiskProvider extends AbstractRiskProvider
{
    private const PHENOMENON_ID = '6';

    public function __construct(
        private HttpClientInterface $httpClient,
        private GeoApiGouvService $geoApiGouvService,
        private string $baseUrl,
        private string $apiToken,
        \Psr\Cache\CacheItemPoolInterface $cache
    ) {
        parent::__construct($cache);
    }

    public function getHazard(): string
    {
        return 'heat';
    }

    protected function getProviderName(): string
    {
        return 'Meteo-France Vigilance (API)';
    }

    protected function getProviderVersion(): string
    {
        return 'DPVigilance v1';
    }

    protected function compute(float $lat, float $lng): RiskDataDTO
    {
        $department = $this->geoApiGouvService->getCommune($lat, $lng)->departmentCode;
        $payload = $this->fetchVigilancePayload();

        $period = $this->resolvePeriod($payload);
        $domain = $this->findDomain($period, $department);

        $phenomenon = $this->findPhenomenon($domain);
        $colorId = (int) ($phenomenon['phenomenon_max_color_id'] ?? 1);
        $colorLabel = $this->colorLabel($colorId);
        $score = $this->scoreFromColor($colorId);

        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'department' => $department,
                'phenomenon_id' => self::PHENOMENON_ID,
                'color_id' => $colorId,
                'color' => $colorLabel,
                'period_id' => $period['periode_id'] ?? null,
                'product_datetime' => $payload['product']['product_datetime'] ?? null,
            ],
            normalizedScore: $score,
            explanation: sprintf(
                'Niveau de vigilance canicule Meteo-France (%s) pour le departement %s.',
                $colorLabel,
                $department
            ),
            confidence: 65,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function fetchVigilancePayload(): array
    {
        $baseUrl = rtrim(trim($this->baseUrl), '/');
        if ($baseUrl === '') {
            throw new DataSourceUnavailableException('Service Meteo-France indisponible.');
        }
        $token = trim($this->apiToken);
        if ($token === '') {
            throw new DataSourceUnavailableException('Token Meteo-France manquant.');
        }

        try {
            $response = $this->httpClient->request('GET', $baseUrl.'/cartevigilance/encours', [
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw new DataSourceUnavailableException('Service Meteo-France indisponible.');
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new DataSourceUnavailableException('Service Meteo-France indisponible (HTTP '.$statusCode.').');
            }
            $payload = $response->getContent(false);
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
            throw new DataSourceUnavailableException('Service Meteo-France indisponible.');
        }

        if (!is_array($data) || !isset($data['product'])) {
            throw new DataSourceUnavailableException('Donnees Meteo-France indisponibles.');
        }

        return $data;
    }

    private function resolvePeriod(array $payload): array
    {
        $periods = $payload['product']['periods'] ?? [];
        if (!is_array($periods) || $periods === []) {
            throw new DataSourceUnavailableException('Donnees Meteo-France indisponibles.');
        }

        foreach ($periods as $period) {
            if (($period['periode_id'] ?? null) === 'J') {
                return $period;
            }
        }

        return $periods[0];
    }

    private function findDomain(array $period, string $department): array
    {
        $domains = $period['timelaps']['domain_ids'] ?? [];
        if (!is_array($domains) || $domains === []) {
            throw new DataSourceUnavailableException('Departement non couvert par Meteo-France.');
        }

        foreach ($domains as $domain) {
            if ((string) ($domain['domain_id'] ?? '') === $department) {
                return $domain;
            }
        }

        throw new DataSourceUnavailableException('Departement non couvert par Meteo-France.');
    }

    private function findPhenomenon(array $domain): array
    {
        $items = $domain['phenomenon_items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if ((string) ($item['phenomenon_id'] ?? '') === self::PHENOMENON_ID) {
                return $item;
            }
        }

        return [];
    }

    private function scoreFromColor(int $colorId): int
    {
        return match ($colorId) {
            2 => 40,
            3 => 70,
            4 => 90,
            default => 10,
        };
    }

    private function colorLabel(int $colorId): string
    {
        return match ($colorId) {
            2 => 'jaune',
            3 => 'orange',
            4 => 'rouge',
            default => 'vert',
        };
    }
}
