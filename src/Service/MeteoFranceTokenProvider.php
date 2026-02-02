<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MeteoFranceTokenProvider
{
    private const USER_AGENT = 'clima-audit-mvp/1.0 (contact: support@example.com)';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $oauthTokenUrl,
        private string $clientId,
        private string $clientSecret,
        private string $applicationId,
        private string $staticToken
    ) {
    }

    public function getToken(): string
    {
        $oauthTokenUrl = trim($this->oauthTokenUrl);
        $clientId = trim($this->clientId);
        $clientSecret = trim($this->clientSecret);
        $applicationId = trim($this->applicationId);
        if ($oauthTokenUrl === '') {
            $fallback = trim($this->staticToken);
            if ($fallback !== '') {
                return $fallback;
            }
            throw new DataSourceUnavailableException('Token Meteo-France manquant.');
        }

        $cacheBasis = $applicationId !== ''
            ? $applicationId
            : $clientId.$clientSecret;
        if ($cacheBasis === '') {
            $fallback = trim($this->staticToken);
            if ($fallback !== '') {
                return $fallback;
            }
            throw new DataSourceUnavailableException('Token Meteo-France manquant.');
        }

        $cacheKey = 'meteo_france_oauth_token_'.substr(sha1($cacheBasis.$oauthTokenUrl), 0, 12);
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            $cached = $item->get();
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        if ($applicationId !== '') {
            [$token, $ttl] = $this->fetchTokenWithApplicationId($oauthTokenUrl, $applicationId);
        } elseif ($clientId !== '' && $clientSecret !== '') {
            [$token, $ttl] = $this->fetchTokenWithClientCredentials($oauthTokenUrl, $clientId, $clientSecret);
        } else {
            $fallback = trim($this->staticToken);
            if ($fallback !== '') {
                return $fallback;
            }
            throw new DataSourceUnavailableException('Token Meteo-France manquant.');
        }
        $item->set($token);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $token;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function fetchTokenWithApplicationId(string $oauthTokenUrl, string $applicationId): array
    {
        return $this->fetchToken($oauthTokenUrl, 'Basic '.$applicationId);
    }

    private function fetchTokenWithClientCredentials(string $oauthTokenUrl, string $clientId, string $clientSecret): array
    {
        $basic = base64_encode($clientId.':'.$clientSecret);

        return $this->fetchToken($oauthTokenUrl, 'Basic '.$basic);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function fetchToken(string $oauthTokenUrl, string $authorization): array
    {
        try {
            $response = $this->httpClient->request('POST', $oauthTokenUrl, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $authorization,
                    'User-Agent' => self::USER_AGENT,
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
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

        if (!is_array($data) || !isset($data['access_token'])) {
            throw new DataSourceUnavailableException('Token Meteo-France indisponible.');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 0);
        $ttl = $expiresIn > 0 ? max(60, $expiresIn - 120) : 600;

        $token = trim((string) $data['access_token']);
        if ($token === '') {
            throw new DataSourceUnavailableException('Token Meteo-France indisponible.');
        }

        return [$token, $ttl];
    }
}
