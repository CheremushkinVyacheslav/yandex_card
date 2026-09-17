<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    public function index(Request $request)
    {
        $org = null;
        if ($request->filled('organization_id')) {
            $org = Organization::find($request->get('organization_id'));
        }
        if (!$org) {
            # сначала успешная
            $org = Organization::where('status', 'success')->latest('parsed_at')->first()
                ?? Organization::latest()->first();
        }
        if (!$org) {
            return response()->json(['message' => 'Организация не найдена'], 404);
        }

        $page = (int) ($request->get('page', 1));
        $sort = $request->get('sort', 'new');
        $lastRun = $org->parseRuns()->orderByDesc('id')->first();
        $lastRunId = $lastRun?->id;
        // базовая линия: сравнение имеет смысл только со второго прогона
        $runsCount = $org->parseRuns()->count();
        $hasBaseline = $runsCount >= 2;

        $reviews = $org->reviews()
            // Дефолт ленты: все активные в порядке источника (ТЗ), удалённые скрыты
            ->when(!$request->boolean('show_deleted') && !$request->boolean('show_deleted_only'), fn ($q) => $q->where('is_deleted', false))
            ->when($request->boolean('show_deleted_only'), fn ($q) => $q->where('is_deleted', true))
            ->when($request->boolean('only_new') && $lastRunId && $hasBaseline, fn ($q) => $q->where('created_in_run_id', $lastRunId))
            ->when($request->boolean('only_changed') && $lastRunId && $hasBaseline, fn ($q) => $q->where('updated_in_run_id', $lastRunId))
            ->when($request->integer('rating'), fn ($q, $r) => $q->where('rating', $r))
            ->when($request->get('q'), fn ($q, $s) => $q->where('text', 'like', "%{$s}%"))
            ->when($sort === 'rating', fn ($q) => $q->orderByDesc('rating')->orderByDesc('review_date'))
            ->when($sort === 'old', fn ($q) => $q->orderBy('review_date'))
            ->when($sort === 'new_first', fn ($q) => $q->orderByDesc('created_in_run_id')->orderByDesc('review_date'))
            ->when(!in_array($sort, ['rating', 'old', 'new_first']), fn ($q) => $q->orderByDesc('review_date'))
            ->paginate(50, ['*'], 'page', $page);

        return response()->json([
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
                'status' => $org->status,
                'last_error' => $org->last_error,
            ],
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_run_id' => $lastRunId,
                'runs_count' => $runsCount,
                'has_baseline' => $hasBaseline,
                'new_count' => ($hasBaseline && $lastRunId) ? $org->reviews()->where('created_in_run_id', $lastRunId)->count() : 0,
                'changed_count' => ($hasBaseline && $lastRunId) ? $org->reviews()->where('updated_in_run_id', $lastRunId)->count() : 0,
                'deleted_count' => $org->reviews()->where('is_deleted', true)->count(),
            ],
            'rating_summary' => [
                'average' => $org->average_rating !== null ? round((float) $org->average_rating, 2) : null,
                'total_ratings' => $org->total_ratings,
                'total_reviews' => $org->total_reviews,
                'distribution' => $org->reviews()
                    ->selectRaw('rating, COUNT(*) as c')
                    ->groupBy('rating')
                    ->orderBy('rating', 'desc')
                    ->pluck('c', 'rating'),
            ],
        ]);
    }

    public function organizations()
    {
        return response()->json(
            Organization::orderBy('id', 'desc')->get([
                'id', 'name', 'yandex_url', 'average_rating', 'total_ratings',
                'total_reviews', 'status', 'last_error', 'parsed_at',
            ])->map(function ($o) {
                $o->average_rating = $o->average_rating !== null ? round((float) $o->average_rating, 2) : null;
                return $o;
            })
        );
    }
}
