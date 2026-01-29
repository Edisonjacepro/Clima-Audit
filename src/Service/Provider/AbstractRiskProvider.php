<?php

namespace App\Service\Provider;

use App\Dto\RiskDataDTO;
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

        $data = $this->compute($lat, $lng);
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

    private function getCacheKey(float $lat, float $lng): string
    {
        return sprintf(
            'risk_%s_%s_%s_%s',
            $this->getHazard(),
            $this->getProviderVersion(),
            number_format($lat, 6, '.', ''),
            number_format($lng, 6, '.', '')
        );
    }
}
