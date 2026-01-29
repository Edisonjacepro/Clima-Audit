<?php

namespace App\Service;

use App\Dto\GeocodingResultDTO;

interface GeocodingServiceInterface
{
    public function geocode(string $address): GeocodingResultDTO;
}
