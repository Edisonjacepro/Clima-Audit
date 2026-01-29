<?php

namespace App\Dto;

class GeocodingResultDTO
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $formattedAddress
    ) {
    }
}
