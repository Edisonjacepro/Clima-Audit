<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HubEauHydrometryClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl
    ) {
    }

    public function fetchNearestStation(float $lat, float $lng, string $communeCode): ?array
    {
        $data = $this->request('/hydrometrie/referentiel/stations', [
            'code_commune_station' => $communeCode,
            'en_service' => 1,
            'size' => 25,
            'format' => 'json',
        ]);

        $stations = $data['data'] ?? [];
        if (!is_array($stations) || $stations === []) {
            return null;
        }

        $bestStation = null;
        $bestDistance = null;

        foreach ($stations as $station) {
            $stationLat = $station['latitude_station'] ?? null;
            $stationLng = $station['longitude_station'] ?? null;
            if (!is_numeric($stationLat) || !is_numeric($stationLng)) {
                continue;
            }
            $distance = $this->distanceKm($lat, $lng, (float) $stationLat, (float) $stationLng);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestStation = $station;
                $bestStation['distance_km'] = round($distance, 1);
            }
        }

        return $bestStation;
    }

    public function fetchLatestObservation(string $stationCode): ?array
    {
        $data = $this->request('/hydrometrie/observations_tr', [
            'code_entite' => $stationCode,
            'grandeur_hydro' => 'H',
            'size' => 20,
            'format' => 'json',
        ]);

        $observations = $data['data'] ?? [];
        if (!is_array($observations) || $observations === []) {
            return null;
        }

        $latest = null;
        $latestDate = null;

        foreach ($observations as $observation) {
            $date = $observation['date_obs'] ?? null;
            if (!is_string($date)) {
                continue;
            }
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                continue;
            }
            if ($latestDate === null || $timestamp > $latestDate) {
                $latestDate = $timestamp;
                $latest = $observation;
            }
        }

        if ($latest === null) {
            return null;
        }

        $result = $latest['resultat_obs'] ?? null;
        if (!is_numeric($result)) {
            return null;
        }

        $latest['height_m'] = round(((float) $result) / 1000, 3);

        return $latest;
    }

    private function request(string $path, array $query): array
    {
        $baseUrl = rtrim(trim($this->baseUrl), '/');
        if ($baseUrl === '') {
            throw new DataSourceUnavailableException('Service Hub\'Eau indisponible.');
        }

        try {
            $response = $this->httpClient->request('GET', $baseUrl.$path, [
                'query' => $query,
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw new DataSourceUnavailableException('Service Hub\'Eau indisponible.');
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new DataSourceUnavailableException('Service Hub\'Eau indisponible (HTTP '.$statusCode.').');
            }
            $payload = $response->getContent(false);
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
            throw new DataSourceUnavailableException('Service Hub\'Eau indisponible.');
        }

        if (isset($data['error'])) {
            $message = 'Service Hub\'Eau indisponible.';
            if (is_array($data['error']) && isset($data['error']['message'])) {
                $message = 'Service Hub\'Eau indisponible: '.$data['error']['message'];
            }
            throw new DataSourceUnavailableException($message);
        }

        return $data;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
