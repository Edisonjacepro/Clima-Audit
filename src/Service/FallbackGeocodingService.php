<?php

namespace App\Service;

use App\Dto\GeocodingResultDTO;

class FallbackGeocodingService implements GeocodingServiceInterface
{
    public function __construct(
        private GeocodingServiceInterface $primary,
        private GeocodingServiceInterface $secondary
    ) {
    }

    public function geocode(string $address): GeocodingResultDTO
    {
        try {
            return $this->primary->geocode($address);
        } catch (GeocodingException $exception) {
            return $this->secondary->geocode($address);
        }
    }
}
