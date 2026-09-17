<?php

namespace App\Console\Commands;

use App\Services\YandexMapsParser;
use App\Services\Yandex\UrlNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ParseOrganizationCommand extends Command
{
    protected $signature = 'parse:org {url : URL страницы отзывов организации} {--org-id= : ID существующей организации}';
    protected $description = 'Парсит отзывы организации с Яндекс Карт';

    public function handle(YandexMapsParser $parser): int
    {
        $url = $this->argument('url');
        $orgId = $this->option('org-id');

        $orgModel = \App\Models\Organization::class;

        if ($orgId) {
            $org = $orgModel::find($orgId);
            if (!$org) {
                $this->error('Организация с ID ' . $orgId . ' не найдена');
                return 1;
            }
        } else {
            # одна запись на карточку
            try {
                $normalizer = app(UrlNormalizer::class);
                $canonical = $normalizer->normalize($url);
                $externalId = $normalizer->extractId($url);
            } catch (\InvalidArgumentException $e) {
                $this->error('Некорректная ссылка: ' . $e->getMessage());
                return 1;
            }
            $org = $orgModel::where('external_id', $externalId)->first();
            if ($org) {
                $org->update(['yandex_url' => $canonical]);
            } else {
                $org = $orgModel::firstOrCreate(
                    ['yandex_url' => $canonical],
                    [
                        'external_id' => $externalId,
                        'name' => 'Новая организация',
                        'status' => 'pending',
                        'rating' => 0,
                        'rating_count' => 0,
                        'review_count' => 0,
                    ]
                );
            }
        }

        $org->update(['status' => 'parsing']);
        $this->info('Запуск парсинга для ' . $org->name . ' (ID: ' . $org->id . ')');

        try {
            $parser->parseOrganization($url, $org->id);
            $this->info('Парсинг завершён успешно. Спарсено отзывов: ' . $org->review_count);
            return 0;
        } catch (\Exception $e) {
            $this->error('Ошибка парсинга: ' . $e->getMessage());
            Log::error('ParseOrganizationCommand failed', ['url' => $url, 'message' => $e->getMessage()]);
            return 1;
        }
    }
}
