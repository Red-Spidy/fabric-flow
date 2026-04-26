<?php

namespace App\Http\Controllers;

use App\Models\FabricBatch;
use App\Models\ManufacturingStage;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Return a high-level pipeline dashboard summary.
     * GET /v1/dashboard
     */
    public function index(): JsonResponse
    {
        // Totals
        $totalBatches   = FabricBatch::count();
        $activeBatches  = FabricBatch::active()->count();
        $delayedBatches = FabricBatch::delayed()->count();
        $completed      = FabricBatch::where('status', 'completed')->count();

        // Batches per stage
        $perStage = [];
        foreach (FabricBatch::STAGES as $stage) {
            $perStage[$stage] = FabricBatch::active()->atStage($stage)->count();
        }

        // Overdue in risk stages
        $overdueCount = FabricBatch::overdueInWeavingOrDyeing()->count();

        // SLA breach rate (closed logs)
        $totalLogs    = \App\Models\FabricBatchStageLog::whereNotNull('exited_at')->count();
        $breachedLogs = \App\Models\FabricBatchStageLog::whereNotNull('exited_at')
            ->where('sla_breached', true)->count();
        $slaBreachRate = $totalLogs > 0
            ? round(($breachedLogs / $totalLogs) * 100, 1)
            : 0;

        // Most recent 5 delayed batches
        $recentDelayed = FabricBatch::delayed()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'batch_id', 'fabric_type', 'current_stage', 'stage_entered_at']);

        return response()->json([
            'summary' => [
                'total_batches'   => $totalBatches,
                'active_batches'  => $activeBatches,
                'delayed_batches' => $delayedBatches,
                'completed'       => $completed,
                'overdue_count'   => $overdueCount,
                'sla_breach_rate' => $slaBreachRate,
            ],
            'batches_per_stage' => $perStage,
            'recent_delayed'    => $recentDelayed,
        ]);
    }
}
