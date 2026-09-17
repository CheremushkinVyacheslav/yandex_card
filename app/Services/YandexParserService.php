<?php

namespace App\Services;

class YandexParserService
{
    public function __construct(protected YandexMapsParser $parser) {}

    public function parse(string $yandexUrl): array
    {
    # старый интерфейс
        $org = \App\Models\Organization::firstOrCreate(
            ['yandex_url' => $yandexUrl],
            ['name' => 'Новая организация', 'status' => 'pending', 'rating' => 0, 'rating_count' => 0, 'review_count' => 0]
        );
        $this->parser->parseOrganization($yandexUrl, $org->id);
        $org->refresh();
        return [
            'name' => $org->name,
            'average_rating' => $org->rating,
            'total_ratings' => $org->rating_count,
            'total_reviews' => $org->review_count,
            'status' => $org->status,
        ];
    }

    public function detectMarkupChange(array $previousData, array $currentData): bool
    {
        return false; // Not used in legacy path; real detection is inside YandexMapsParser
    }
}
