<?php

namespace App\Service;

use App\Dto\GeocodingResultDTO;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IgnGeocodingService implements GeocodingServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl
    ) {
    }

    public function geocode(string $address): GeocodingResultDTO
    {
        $baseUrl = rtrim(trim($this->baseUrl), '/');
        if ($baseUrl === '') {
            throw new GeocodingException('Service de geocodage indisponible.');
        }

        try {
            $response = $this->httpClient->request('GET', $baseUrl.'/search', [
                'query' => [
                    'q' => trim($address),
                    'limit' => 1,
                ],
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'fr',
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw new GeocodingException('Service de geocodage indisponible.');
        }

        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new GeocodingException('Service de geocodage indisponible (HTTP '.$statusCode.').');
            }
            $payload = $response->getContent(false);
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
            throw new GeocodingException('Service de geocodage indisponible.');
        }

        $features = $data['features'] ?? [];
        if (empty($features)) {
            throw new GeocodingException('Adresse introuvable. Merci de saisir une adresse complete.');
        }

        $feature = $features[0];
        $geometry = $feature['geometry']['coordinates'] ?? null;
        $properties = $feature['properties'] ?? [];

        if (!is_array($geometry) || count($geometry) < 2) {
            throw new GeocodingException('Adresse introuvable. Merci de saisir une adresse complete.');
        }

        $lng = (float) $geometry[0];
        $lat = (float) $geometry[1];
        $label = (string) ($properties['label'] ?? $address);

        if (!$this->isCompleteAddress($properties)) {
            throw new GeocodingException('Adresse incomplete. Merci d\'indiquer numero, rue, code postal et ville.');
        }

        return new GeocodingResultDTO(
            lat: $lat,
            lng: $lng,
            formattedAddress: $label
        );
    }

    private function isCompleteAddress(array $properties): bool
    {
        $hasNumber = !empty($properties['housenumber']);
        $hasStreet = !empty($properties['street']) || !empty($properties['name']);
        $hasPostcode = !empty($properties['postcode']);
        $hasCity = !empty($properties['city']) || !empty($properties['citycode']) || !empty($properties['municipality']);

        return $hasNumber && $hasStreet && $hasPostcode && $hasCity;
    }
}
