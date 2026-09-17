<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ParseRun;
use Illuminate\Http\Request;

class ParseRunsController extends Controller
{
    protected function resolveOrg(Request $request): ?Organization
    {
        if ($request->filled('organization_id')) {
            return Organization::find($request->get('organization_id'));
        }

        return Organization::where('status', 'success')->latest('parsed_at')->first()
            ?? Organization::latest()->first();
    }

    # список прогонов
    public function index(Request $request)
    {
        $org = $this->resolveOrg($request);
        if (!$org) {
            return response()->json(['message' => 'Организация не найдена'], 404);
        }

        return response()->json([
            'organization' => ['id' => $org->id, 'name' => $org->name],
            'data' => $org->parseRuns()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    # детали прогона
    public function show(Request $request, int $id)
    {
        $run = ParseRun::find($id);
        if (!$run) {
            return response()->json(['message' => 'Запуск не найден'], 404);
        }

        $changes = $run->changes()
            ->with('review:id,organization_id,author,rating')
            ->orderBy('id')
            ->get();

        $deleted = $run->organization->reviews()
            ->where('deleted_in_run_id', $run->id)
            ->orderByDesc('review_date')
            ->limit(200)
            ->get(['id', 'author', 'rating', 'text', 'review_date']);

        return response()->json([
            'run' => $run,
            'changes' => $changes,
            'deleted' => $deleted,
        ]);
    }
}
