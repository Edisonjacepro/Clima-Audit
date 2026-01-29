<?php

namespace App\Service;

use App\Dto\CommuneDTO;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoApiGouvService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $baseUrl
    ) {
    }

    public function getCommune(float $lat, float $lng): CommuneDTO
    {
        $cacheKey = $this->getCacheKey($lat, $lng);
        $cached = $this->cache->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        $baseUrl = rtrim(trim($this->baseUrl), '/');
        if ($baseUrl === '') {
            throw new DataSourceUnavailableException('Service Geo API Gouv indisponible.');
        }

        try {
            $response = $this->httpClient->request('GET', $baseUrl.'/communes', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lng,
                    'fields' => 'code,nom,codeDepartement,departement,codesPostaux',
                    'format' => 'json',
                ],
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw new DataSourceUnavailableException('Service Geo API Gouv indisponible.');
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new DataSourceUnavailableException('Service Geo API Gouv indisponible (HTTP '.$statusCode.').');
            }
            $payload = $response->getContent(false);
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
            throw new DataSourceUnavailableException('Service Geo API Gouv indisponible.');
        }

        if (!is_array($data) || $data === []) {
            throw new DataSourceUnavailableException('Commune introuvable pour ces coordonnees.');
        }

        $commune = $data[0];
        $code = (string) ($commune['code'] ?? '');
        $name = (string) ($commune['nom'] ?? '');
        $departmentCode = (string) ($commune['codeDepartement'] ?? '');
        $departmentName = (string) (($commune['departement']['nom'] ?? '') ?: ($commune['departement'] ?? ''));
        $postalCodes = $commune['codesPostaux'] ?? [];

        if ($code === '' || $departmentCode === '') {
            throw new DataSourceUnavailableException('Commune introuvable pour ces coordonnees.');
        }

        $dto = new CommuneDTO(
            code: $code,
            name: $name !== '' ? $name : $code,
            departmentCode: $departmentCode,
            departmentName: $departmentName !== '' ? $departmentName : $departmentCode,
            postalCodes: is_array($postalCodes) ? $postalCodes : []
        );

        $cached->set($dto);
        $cached->expiresAfter(86400);
        $this->cache->save($cached);

        return $dto;
    }

    private function getCacheKey(float $lat, float $lng): string
    {
        return sprintf(
            'geoapi_commune_%s_%s',
            number_format($lat, 5, '.', ''),
            number_format($lng, 5, '.', '')
        );
    }
}
