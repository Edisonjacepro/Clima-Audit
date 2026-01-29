<?php

namespace App\Service;

use App\Dto\GeocodingResultDTO;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NominatimGeocodingService implements GeocodingServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $userAgent,
        private string $email
    ) {
    }

    public function geocode(string $address): GeocodingResultDTO
    {
        $baseUrl = rtrim(trim($this->baseUrl), '/');
        $userAgent = trim($this->userAgent);
        $email = trim($this->email);
        if ($userAgent === '') {
            $userAgent = 'clima-audit-mvp/1.0 (contact: support@example.com)';
        }
        if ($baseUrl === '') {
            throw new GeocodingException('Service de geocodage indisponible.');
        }

        try {
            $query = [
                'q' => trim($address),
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 1,
            ];
            if ($email !== '') {
                $query['email'] = $email;
            }

            $response = $this->httpClient->request('GET', $baseUrl.'/search', [
                'query' => $query,
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => $userAgent,
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
        if (empty($data)) {
            throw new GeocodingException('Adresse introuvable. Merci de saisir une adresse complete.');
        }

        $result = $data[0];
        $lat = (float) ($result['lat'] ?? 0);
        $lng = (float) ($result['lon'] ?? 0);
        $displayName = (string) ($result['display_name'] ?? $address);
        $addressParts = (array) ($result['address'] ?? []);

        if ($lat === 0.0 && $lng === 0.0) {
            throw new GeocodingException('Adresse introuvable. Merci de saisir une adresse complete.');
        }
        if (!$this->isCompleteAddress($addressParts)) {
            throw new GeocodingException('Adresse incomplete. Merci d\'indiquer numero, rue, code postal et ville.');
        }

        return new GeocodingResultDTO(
            lat: $lat,
            lng: $lng,
            formattedAddress: $displayName
        );
    }

    private function isCompleteAddress(array $address): bool
    {
        $hasRoad = isset($address['road']) || isset($address['pedestrian']) || isset($address['footway']);
        $hasNumber = isset($address['house_number']);
        $hasPostcode = isset($address['postcode']);
        $hasCity = isset($address['city']) || isset($address['town']) || isset($address['village']) || isset($address['municipality']);

        return $hasRoad && $hasCity && ($hasPostcode || $hasNumber);
    }
}
