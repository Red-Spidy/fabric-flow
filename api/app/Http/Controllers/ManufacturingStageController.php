<?php

namespace App\Http\Controllers;

use App\Models\ManufacturingStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturingStageController extends Controller
{
    /**
     * List all stages in pipeline order.
     * GET /v1/manufacturing-stages
     */
    public function index(): JsonResponse
    {
        $stages = ManufacturingStage::ordered()->get();
        return response()->json($stages);
    }

    /**
     * Show a single stage.
     * GET /v1/manufacturing-stages/{id}
     */
    public function show(string $id): JsonResponse
    {
        $stage = ManufacturingStage::findOrFail($id);
        return response()->json($stage);
    }

    /**
     * Show pipeline summary — stages with their batch counts.
     * GET /v1/manufacturing-stages/summary
     */
    public function summary(): JsonResponse
    {
        $stages = ManufacturingStage::ordered()->withCount([
            'stageLogs as open_batches_count' => function ($q) {
                $q->whereNull('exited_at');
            },
            'stageLogs as breached_count' => function ($q) {
                $q->where('sla_breached', true);
            },
        ])->get();

        return response()->json($stages);
    }
}
