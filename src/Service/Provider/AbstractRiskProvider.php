<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
use App\Service\DataSourceUnavailableException;
use App\Service\RiskDataProviderInterface;
use Psr\Cache\CacheItemPoolInterface;

abstract class AbstractRiskProvider implements RiskDataProviderInterface
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    abstract protected function getProviderName(): string;

    abstract protected function getProviderVersion(): string;

    abstract protected function compute(float $lat, float $lng): RiskDataDTO;

    public function fetch(float $lat, float $lng): RiskDataDTO
    {
        $key = $this->getCacheKey($lat, $lng);

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        try {
            $data = $this->compute($lat, $lng);
        } catch (DataSourceUnavailableException $exception) {
            $data = $this->buildUnavailableDto($exception->getMessage(), $lat, $lng);
        }
        $item->set($data);
        $item->expiresAfter(86400);
        $this->cache->save($item);

        return $data;
    }

    protected function buildSourceMeta(float $lat, float $lng): array
    {
        return [
            'name' => $this->getProviderName(),
            'version' => $this->getProviderVersion(),
            'fetchedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'params' => ['lat' => $lat, 'lng' => $lng],
        ];
    }

    protected function buildUnavailableDto(string $message, float $lat, float $lng): RiskDataDTO
    {
        return new RiskDataDTO(
            hazard: $this->getHazard(),
            rawIndicators: [
                'status' => 'unavailable',
                'error' => $message,
            ],
            normalizedScore: 0,
            explanation: $message,
            confidence: 0,
            sourceMeta: $this->buildSourceMeta($lat, $lng)
        );
    }

    private function getCacheKey(float $lat, float $lng): string
    {
        $version = $this->sanitizeCacheToken($this->getProviderVersion());
        return sprintf(
            'risk_%s_%s_%s_%s',
            $this->getHazard(),
            $version,
            number_format($lat, 6, '.', ''),
            number_format($lng, 6, '.', '')
        );
    }

    private function sanitizeCacheToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_.-]+/', '_', $normalized);

        return $normalized === '' ? 'na' : $normalized;
    }
}
