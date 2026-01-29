<?php

namespace App\Service;

use App\Dto\RiskDataDTO;

interface RiskDataProviderInterface
{
    public function getHazard(): string;

    public function fetch(float $lat, float $lng): RiskDataDTO;
}
