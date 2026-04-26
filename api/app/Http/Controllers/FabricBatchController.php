<?php

namespace App\Http\Controllers;

use App\Models\FabricBatch;
use App\Models\ManufacturingStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FabricBatchController extends Controller
{
    /**
     * List all fabric batches (with optional filters).
     * GET /v1/fabric-batches
     */
    public function index(Request $request): JsonResponse
    {
        $query = FabricBatch::with(['activeStageLog', 'stageLogs']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('stage')) {
            $query->atStage($request->stage);
        }

        if ($request->filled('fabric_type')) {
            $query->where('fabric_type', $request->fabric_type);
        }

        if ($request->boolean('delayed')) {
            $query->delayed();
        }

        $batches = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($batches);
    }

    /**
     * Create a new fabric batch.
     * POST /v1/fabric-batches
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id'                 => 'required|string|max:64|unique:fabric_batches,batch_id',
            'fabric_type'              => ['required', Rule::in(FabricBatch::FABRIC_TYPES)],
            'quantity_kg'              => 'required|numeric|min:0.001',
            'origin'                   => 'required|string|max:255',
            'supplier_name'            => 'nullable|string|max:255',
            'supplier_contact'         => 'nullable|string|max:255',
            'expected_completion_date' => 'nullable|date',
        ]);

        $validated['current_stage']    = 'Processing';
        $validated['status']           = 'on_track';
        $validated['stage_entered_at'] = now();

        $batch = FabricBatch::create($validated);

        // Open the initial stage log for Processing
        $stage = ManufacturingStage::where('stage_name', 'Processing')->first();
        if ($stage) {
            $batch->stageLogs()->create([
                'manufacturing_stage_id' => $stage->id,
                'stage_name'             => 'Processing',
                'entered_at'             => now(),
                'transitioned_by'        => 'system',
                'notes'                  => 'Batch created — entered Processing stage.',
            ]);
        }

        return response()->json($batch->fresh(['stageLogs', 'activeStageLog']), 201);
    }

    /**
     * Show a single fabric batch with full history.
     * GET /v1/fabric-batches/{id}
     */
    public function show(string $id): JsonResponse
    {
        $batch = FabricBatch::with(['stageLogs.manufacturingStage', 'activeStageLog'])
            ->findOrFail($id);

        return response()->json($batch);
    }

    /**
     * Update a fabric batch's metadata (not stage — use advance for that).
     * PATCH /v1/fabric-batches/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $batch = FabricBatch::findOrFail($id);

        $validated = $request->validate([
            'fabric_type'              => ['sometimes', Rule::in(FabricBatch::FABRIC_TYPES)],
            'quantity_kg'              => 'sometimes|numeric|min:0.001',
            'origin'                   => 'sometimes|string|max:255',
            'supplier_name'            => 'nullable|string|max:255',
            'supplier_contact'         => 'nullable|string|max:255',
            'expected_completion_date' => 'nullable|date',
            'status'                   => ['sometimes', Rule::in(FabricBatch::STATUSES)],
        ]);

        $batch->update($validated);

        return response()->json($batch->fresh(['stageLogs', 'activeStageLog']));
    }

    /**
     * Advance a batch to a new manufacturing stage.
     * POST /v1/fabric-batches/{id}/advance
     */
    public function advance(Request $request, string $id): JsonResponse
    {
        $batch = FabricBatch::findOrFail($id);

        $validated = $request->validate([
            'stage'            => ['required', Rule::in(FabricBatch::STAGES)],
            'transitioned_by'  => 'nullable|string|max:191',
            'notes'            => 'nullable|string|max:1000',
        ]);

        if ($validated['stage'] === $batch->current_stage) {
            return response()->json([
                'error' => "Batch is already at stage [{$batch->current_stage}].",
            ], 422);
        }

        $log = $batch->advanceToStage(
            $validated['stage'],
            $validated['transitioned_by'] ?? 'system',
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message'      => "Batch advanced to [{$validated['stage']}].",
            'batch'        => $batch->fresh(['stageLogs', 'activeStageLog']),
            'new_log_entry' => $log,
        ]);
    }

    /**
     * Delete (soft-delete) a batch.
     * DELETE /v1/fabric-batches/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $batch = FabricBatch::findOrFail($id);
        $batch->delete();

        return response()->json(['message' => 'Batch deleted.']);
    }

    /**
     * Get the full stage transition history for a batch.
     * GET /v1/fabric-batches/{id}/logs
     */
    public function logs(string $id): JsonResponse
    {
        $batch = FabricBatch::findOrFail($id);

        $logs = $batch->stageLogs()
            ->with('manufacturingStage')
            ->orderBy('entered_at')
            ->get();

        return response()->json($logs);
    }
}
