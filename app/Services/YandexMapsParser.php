<?php

namespace App\Services;

use App\Clients\YandexMapsClient;
use App\DTOs\OrganizationData;
use App\DTOs\ReviewData;
use App\Exceptions\SourceChangedException;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Models\ReviewChange;
use App\Models\ReviewSnapshot;
use App\Services\Yandex\UrlNormalizer;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    protected string $parserVersion = 'v2.0-ssr';

    public function __construct(protected YandexMapsClient $client) {}

    public function parseOrganization(string $url, int $organizationId): void
    {
        $url = app(UrlNormalizer::class)->normalize($url);

        $org = Organization::findOrFail($organizationId);
        $org->update(['status' => 'parsing']);

        $run = ParseRun::create([
            'organization_id' => $org->id,
            'status' => 'parsing',
            'parser_version' => $this->parserVersion,
            'started_at' => now(),
        ]);

        $baseUrl = $url;
        if (!str_ends_with($url, '/reviews/')) {
            $baseUrl = rtrim($url, '/') . '/reviews/';
        } else {
            $baseUrl = rtrim($url, '/') . '/';
        }
        $currentPage = 1;
        $allReviews = collect();

        try {
            $firstPageHtml = $this->client->fetchPage($baseUrl);
            $crawler = new Crawler($firstPageHtml);

            if ($this->client->detectMarkupChange($crawler)) {
                throw new SourceChangedException('Разметка изменилась: отсутствуют ключевые селекторы');
            }

            $stateData = $this->client->fetchStateView($crawler);
            $orgData = $this->extractOrganizationData($stateData, $baseUrl);

            $org->update([
                'external_id' => $orgData->externalId,
                'name' => $orgData->name,
                'address' => $orgData->address,
                'rating' => $orgData->rating,
                'rating_count' => $orgData->ratingCount,
                'review_count' => $orgData->reviewCount,
                'rubrics' => $orgData->rubrics,
                'yandex_url' => $baseUrl,
                # поля для фронта
                'average_rating' => $orgData->rating,
                'total_ratings' => $orgData->ratingCount,
                'total_reviews' => $orgData->reviewCount,
            ]);

            # первая страница
            $reviews = $this->extractReviewsFromCrawler($crawler, $orgData->externalId);
            $allReviews = $allReviews->merge($reviews);

            # листаем страницы
            $maxPages = 40; # кап страниц
            $seen = $reviews->map(fn ($r) => $r->externalId);
            while ($currentPage < $maxPages) {
                usleep(1500000); # пауза от бана
                $currentPage++;
                $pageUrl = $baseUrl . '?page=' . $currentPage;
                $pageHtml = $this->client->fetchPage($pageUrl);
                $pageCrawler = new Crawler($pageHtml);

                if ($this->client->detectMarkupChange($pageCrawler)) {
                    Log::warning('Markup change detected at page ' . $currentPage);
                    break;
                }

                $reviews = $this->extractReviewsFromCrawler($pageCrawler, $orgData->externalId);
                $newIds = $reviews->map(fn ($r) => $r->externalId)->reject(fn ($id) => $seen->contains($id));
                if ($reviews->isEmpty() || $newIds->isEmpty()) {
                    break; // последняя страница или Яндекс вернул первую снова
                }
                $seen = $seen->merge($newIds);
                $allReviews = $allReviews->merge($reviews);
            }

            $this->applyDiff($org, $run, $allReviews, $orgData->reviewCount);
            $this->createSnapshot($org, $allReviews, 'ok');
            $org->update([
                'status' => 'success',
                'parsed_at' => now(),
                'last_error' => null,
            ]);
            $run->update([
                'status' => 'success',
                'finished_at' => now(),
            ]);

            Log::info('Parser completed', [
                'org_id' => $org->id,
                'run_id' => $run->id,
                'reviews_parsed' => $allReviews->count(),
                'pages' => $currentPage,
            ]);
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            $org->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            Log::error('Parser failed', ['org_id' => $org->id, 'run_id' => $run->id, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function extractOrganizationData(array $stateData, string $baseUrl): OrganizationData
    {
        # путь зависит от страницы
        $item = $stateData['stack'][0]['response']['items'][0]
            ?? $stateData['stack'][0]['results']['items'][0]
            ?? null;
        if (!$item) {
            throw new SourceChangedException('Отсутствуют данные организации в state-view');
        }

        $ratingData = $item['ratingData'] ?? [];
        $rubrics = collect($item['categories'] ?? [])->pluck('name')->toArray();

        return new OrganizationData(
            externalId: (string) ($item['id'] ?? ''),
            name: $item['title'] ?? '',
            address: $item['fullAddress'] ?? '',
            rating: (float) ($ratingData['ratingValue'] ?? 0),
            ratingCount: (int) ($ratingData['ratingCount'] ?? 0),
            reviewCount: (int) ($ratingData['reviewCount'] ?? 0),
            rubrics: $rubrics,
            url: $baseUrl,
        );
    }

    protected function extractReviewsFromCrawler(Crawler $crawler, string $orgExternalId): \Illuminate\Support\Collection
    {
        $reviews = collect();
        $nodes = $crawler->filter('[itemType="http://schema.org/Review"]');

        foreach ($nodes as $node) {
            $nodeCrawler = new Crawler($node);

            # защита от пустых нод
            $textOf = function (string $selector) use ($nodeCrawler): string {
                $c = $nodeCrawler->filter($selector);
                return $c->count() > 0 ? trim($c->first()->text('')) : '';
            };
            $attrOf = function (string $selector, string $attr) use ($nodeCrawler): string {
                $c = $nodeCrawler->filter($selector);
                return $c->count() > 0 ? (string) $c->first()->attr($attr) : '';
            };

            $author = $textOf('[itemProp="name"]');
            $authorLevel = $textOf('.business-review-view__author-caption');
            $text = $textOf('[itemProp="reviewBody"]');
            if ($text === '') {
                $text = $textOf('.business-review-view__body');
            }

            $dateText = $attrOf('[itemProp="datePublished"]', 'content');
            if ($dateText === '') {
                $dateText = $textOf('.business-review-view__date');
            }
            $date = $this->client->parseRussianDate($dateText);

            $rating = 0;
            $ratingMeta = $attrOf('[itemProp="ratingValue"]', 'content');
            if ($ratingMeta !== '') {
                $rating = (int) round((float) $ratingMeta);
            } else {
                $rating = $nodeCrawler->filter('.business-rating-badge-view__star._full')->count();
                if ($rating === 0) {
                    $rating = $nodeCrawler->filter('.business-rating-badge-view__star._empty')->count() > 0 ? 1 : 0;
                }
            }
            if ($rating === 0) {
                $rating = 5; // фолбэк: Яндекс не всегда кладёт разметку звёзд
            }

            $businessReply = $textOf('.business-review-view__business-comment .business-review-comment-content__bubble');

            $likes = 0;
            $dislikes = 0;
            $reactions = $nodeCrawler->filter('.business-reactions-view__counter');
            if ($reactions->count() >= 1) {
                $likes = (int) preg_replace('/\D/', '', trim($reactions->eq(0)->text(''))) ?: 0;
            }
            if ($reactions->count() >= 2) {
                $dislikes = (int) preg_replace('/\D/', '', trim($reactions->eq(1)->text(''))) ?: 0;
            }

            # аватар хотлинком
            $avatarUrl = $attrOf('[itemProp="author"] [itemProp="image"]', 'content');
            if ($avatarUrl === '') {
                $avatarUrl = $attrOf('[itemProp="image"]', 'content');
            }

            if ($author === '' && $text === '') {
                continue; # пропуск мусора
            }

            $externalId = hash('sha1', $author . $text . $rating);

            $reviews->push(new ReviewData(
                author: $author,
                authorLevel: $authorLevel ?: null,
                rating: max(1, min(5, $rating)),
                text: $text,
                reviewDate: $date,
                businessReply: $businessReply ?: null,
                businessReplyDate: null,
                likes: $likes,
                dislikes: $dislikes,
                externalId: $externalId,
                avatarUrl: $avatarUrl !== '' ? $avatarUrl : null,
            ));
        }

        return $reviews;
    }

    # сверка с базой
    protected function applyDiff(Organization $org, ParseRun $run, \Illuminate\Support\Collection $reviews, int $expectedTotal): void
    {
        $fetched = $reviews->keyBy(fn ($r) => $r->externalId);
        $existing = Review::where('organization_id', $org->id)->get()->keyBy('external_id');

        $new = 0;
        $changed = 0;

        foreach ($fetched as $externalId => $dto) {
            $row = $existing->get($externalId);
            if (!$row) {
                Review::create([
                    'organization_id' => $org->id,
                    'external_id' => $externalId,
                    'source_id' => $externalId, # старое поле
                    'author' => $dto->author,
                    'author_name' => $dto->author, # старое поле
                    'author_level' => $dto->authorLevel,
                    'rating' => $dto->rating,
                    'text' => $dto->text,
                    'review_date' => $dto->reviewDate,
                    'business_reply' => $dto->businessReply,
                    'likes' => $dto->likes,
                    'dislikes' => $dto->dislikes,
                    'avatar_url' => $dto->avatarUrl,
                    'created_in_run_id' => $run->id,
                ]);
                $new++;
                continue;
            }

            $diffs = [];
            if (($row->text ?? '') !== $dto->text) {
                $diffs['text'] = [(string) $row->text, $dto->text];
            }
            if ((int) $row->rating !== $dto->rating) {
                $diffs['rating'] = [(string) $row->rating, (string) $dto->rating];
            }
            if (($row->author ?? '') !== $dto->author) {
                $diffs['author'] = [(string) $row->author, $dto->author];
            }
            foreach ($diffs as $field => [$old, $newValue]) {
                ReviewChange::create([
                    'review_id' => $row->id,
                    'parse_run_id' => $run->id,
                    'field' => $field,
                    'old_value' => mb_substr((string) $old, 0, 5000),
                    'new_value' => mb_substr((string) $newValue, 0, 5000),
                ]);
            }

            $row->update([
                'author' => $dto->author,
                'author_name' => $dto->author,
                'author_level' => $dto->authorLevel,
                'rating' => $dto->rating,
                'text' => $dto->text,
                'review_date' => $dto->reviewDate,
                'business_reply' => $dto->businessReply,
                'likes' => $dto->likes,
                'dislikes' => $dto->dislikes,
                'avatar_url' => $dto->avatarUrl,
                'is_deleted' => false,
                'deleted_in_run_id' => null,
                'updated_in_run_id' => $diffs ? $run->id : $row->updated_in_run_id,
            ]);
            if ($diffs) {
                $changed++;
            }
        }

        # метка удалённых
        $deleted = 0;
        if ($fetched->count() >= max(1, (int) ($expectedTotal * 0.9))) {
            $missing = $existing->keys()->diff($fetched->keys());
            foreach ($missing->chunk(500) as $chunk) {
                $deleted += Review::where('organization_id', $org->id)
                    ->whereIn('external_id', $chunk->all())
                    ->where('is_deleted', false)
                    ->update(['is_deleted' => true, 'deleted_in_run_id' => $run->id]);
            }
        } else {
            Log::info('Partial fetch — skip deleted-marking', [
                'org_id' => $org->id,
                'fetched' => $fetched->count(),
                'expected' => $expectedTotal,
            ]);
        }

        $run->update([
            'found_total' => $fetched->count(),
            'new_count' => $new,
            'changed_count' => $changed,
            'deleted_count' => $deleted,
        ]);
    }

    protected function createSnapshot(Organization $org, \Illuminate\Support\Collection $reviews, string $status): void
    {
        ReviewSnapshot::create([
            'organization_id' => $org->id,
            'total_reviews' => $reviews->count(),
            'average_rating' => $org->rating,
            'metrics_snapshot' => [
                'parser_version' => $this->parserVersion,
                'reviews_parsed' => $reviews->count(),
                'pages_estimated' => ceil($reviews->count() / 25),
            ],
            'parser_version' => $this->parserVersion,
            'status' => $status,
        ]);
    }
}
