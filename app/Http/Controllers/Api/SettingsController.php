<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Jobs\ParseYandexReviewsJob;
use App\Models\Organization;
use App\Services\Yandex\UrlNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function store(StoreOrganizationRequest $request, UrlNormalizer $normalizer)
    {
        $validated = $request->validated();
        $url = $normalizer->normalize($validated['yandex_url']);
        $externalId = $normalizer->extractId($validated['yandex_url']);

        # без дублей
        $org = Organization::where('external_id', $externalId)->first();
        if ($org) {
            $org->update(['yandex_url' => $url]);
        } else {
            $org = Organization::firstOrCreate(
                ['yandex_url' => $url],
                ['external_id' => $externalId, 'name' => 'Новая организация', 'average_rating' => 0, 'total_ratings' => 0, 'total_reviews' => 0]
            );
        }

        $org->update(['yandex_url' => $url, 'name' => $org->name ?? 'Новая организация']);

        # парсинг в очередь
        ParseYandexReviewsJob::dispatch($org);

        return response()->json([
            'message' => 'Ссылка сохранена, парсинг запущен в очереди',
            'organization' => $org,
        ]);
    }

    public function show(Request $request)
    {
        $org = Organization::latest()->first();
        if (!$org) {
            return response()->json(['message' => 'Организация не найдена'], 404);
        }

        return response()->json([
            'organization' => $org,
            'reviews_paged' => $org->reviews()->orderBy('review_date', 'desc')->paginate(50),
        ]);
    }

    public function index(Request $request)
    {
        $org = Organization::latest()->first();
        if (!$org) {
            return response()->json(['message' => 'Организация не найдена'], 404);
        }

        $page = $request->get('page', 1);
        $reviews = $org->reviews()->orderBy('review_date', 'desc')->paginate(50, ['*'], 'page', $page);

        return response()->json([
            'organization' => $org,
            'reviews' => $reviews,
        ]);
    }
}
