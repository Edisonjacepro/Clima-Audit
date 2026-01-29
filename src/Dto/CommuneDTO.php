<?php

namespace App\Dto;

class CommuneDTO
{
    /**
     * @param string[] $postalCodes
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $departmentCode,
        public readonly string $departmentName,
        public readonly array $postalCodes = []
    ) {
    }
}
