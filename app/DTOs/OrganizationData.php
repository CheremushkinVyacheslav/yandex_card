<?php

namespace App\DTOs;

readonly class OrganizationData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $address,
        public float $rating,
        public int $ratingCount,
        public int $reviewCount,
        public array $rubrics,
        public string $url,
    ) {}
}
