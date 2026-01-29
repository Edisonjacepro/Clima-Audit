<?php

namespace App\Dto;

class ScoringResultDTO
{
    public function __construct(
        public readonly array $scores,
        public readonly array $levels,
        public readonly int $globalScore,
        public readonly string $globalLevel,
        public readonly int $confidenceScore,
        public readonly array $explanations,
        public readonly array $dataSources
    ) {
    }
}
