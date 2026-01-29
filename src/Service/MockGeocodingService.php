<?php

namespace App\Service;

use App\Dto\GeocodingResultDTO;

class MockGeocodingService implements GeocodingServiceInterface
{
    public function geocode(string $address): GeocodingResultDTO
    {
        $hash = crc32(mb_strtolower(trim($address)));

        $lat = 42.0 + (($hash % 1000) / 1000.0) * 9.0;
        $lng = -4.5 + ((($hash / 1000) % 1000) / 1000.0) * 11.5;

        return new GeocodingResultDTO(
            lat: round($lat, 6),
            lng: round($lng, 6),
            formattedAddress: $address
        );
    }
}
