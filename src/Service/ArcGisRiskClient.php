<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ArcGisRiskClient
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function fetchFirstAttributes(string $layerUrl, float $lat, float $lng, array $outFields = ['*']): ?array
    {
        $payload = $this->request($layerUrl, $lat, $lng, [
            'resultRecordCount' => 1,
            'outFields' => $this->formatFields($outFields),
            'returnGeometry' => 'false',
        ]);

        $features = $payload['features'] ?? [];
        if ($features === [] || !isset($features[0]['attributes'])) {
            return null;
        }

        return $features[0]['attributes'];
    }

    public function fetchCount(string $layerUrl, float $lat, float $lng, ?int $distanceMeters = null): int
    {
        $payload = $this->request($layerUrl, $lat, $lng, array_filter([
            'returnCountOnly' => 'true',
            'distance' => $distanceMeters,
            'units' => $distanceMeters !== null ? 'esriSRUnit_Meter' : null,
        ], static fn ($value) => $value !== null));

        $count = $payload['count'] ?? 0;

        return is_numeric($count) ? (int) $count : 0;
    }

    private function request(string $layerUrl, float $lat, float $lng, array $extraQuery = []): array
    {
        $url = $this->buildQueryUrl($layerUrl);
        $query = array_filter(array_merge([
            'f' => 'json',
            'where' => '1=1',
            'geometry' => sprintf('%F,%F', $lng, $lat),
            'geometryType' => 'esriGeometryPoint',
            'inSR' => 4326,
            'spatialRel' => 'esriSpatialRelIntersects',
            'returnGeometry' => 'false',
        ], $extraQuery), static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $query,
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw new DataSourceUnavailableException('Service ArcGIS indisponible.');
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new DataSourceUnavailableException('Service ArcGIS indisponible (HTTP '.$statusCode.').');
            }
            $payload = $response->getContent(false);
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
            throw new DataSourceUnavailableException('Service ArcGIS indisponible.');
        }

        if (isset($data['error'])) {
            $message = 'Service ArcGIS indisponible.';
            if (is_array($data['error'])) {
                $detail = $data['error']['message'] ?? null;
                if (isset($data['error']['details']) && is_array($data['error']['details'])) {
                    $detail = trim(($detail ?? '').' '.implode(' ', $data['error']['details']));
                }
                if (is_string($detail) && $detail !== '') {
                    $message = 'Service ArcGIS indisponible: '.$detail;
                }
            }
            throw new DataSourceUnavailableException($message);
        }

        return $data;
    }

    private function buildQueryUrl(string $layerUrl): string
    {
        $baseUrl = rtrim(trim($layerUrl), '/');
        if ($baseUrl === '') {
            throw new DataSourceUnavailableException('Source ArcGIS manquante.');
        }

        if (str_ends_with($baseUrl, '/query')) {
            return $baseUrl;
        }

        return $baseUrl.'/query';
    }

    private function formatFields(array $fields): string
    {
        if ($fields === [] || in_array('*', $fields, true)) {
            return '*';
        }

        return implode(',', $fields);
    }
}
