<?php

namespace App\Dto;

class RiskDataDTO
{
    public function __construct(
        public readonly string $hazard,
        public readonly array $rawIndicators,
        public readonly int $normalizedScore,
        public readonly string $explanation,
        public readonly int $confidence,
        public readonly array $sourceMeta
    ) {
    }
}
