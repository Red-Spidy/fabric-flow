<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FabricBatch extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'fabric_batches';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'batch_id',
        'fabric_type',
        'quantity_kg',
        'origin',
        'current_stage',
        'status',
        'stage_entered_at',
        'fleetbase_order_uuid',
        'company_uuid',
        'supplier_name',
        'supplier_contact',
        'buyer_name',
        'buyer_email',
        'expected_completion_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'quantity_kg'              => 'float',
        'stage_entered_at'         => 'datetime',
        'expected_completion_date' => 'date',
    ];

    /**
     * Valid fabric types.
     */
    public const FABRIC_TYPES = ['cotton', 'silk', 'linen', 'polyester', 'wool'];

    /**
     * Valid pipeline stages.
     */
    public const STAGES = ['Processing', 'Weaving', 'Dyeing', 'Warehouse', 'Shipped'];

    /**
     * Valid batch statuses.
     */
    public const STATUSES = ['on_track', 'delayed', 'completed', 'cancelled'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * All stage transition logs for this batch.
     */
    public function stageLogs(): HasMany
    {
        return $this->hasMany(FabricBatchStageLog::class, 'fabric_batch_id');
    }

    /**
     * The currently active (open) stage log entry.
     * "Open" = exited_at IS NULL.
     */
    public function activeStageLog(): HasOne
    {
        return $this->hasOne(FabricBatchStageLog::class, 'fabric_batch_id')
                    ->whereNull('exited_at')
                    ->latest('entered_at');
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Advance the batch to the next manufacturing stage.
     * Closes the current open log entry and opens a new one.
     *
     * @param  string       $newStage      One of self::STAGES
     * @param  string|null  $transitionedBy  User UUID or 'system'
     * @param  string|null  $notes
     * @return FabricBatchStageLog  The newly created log entry
     */
    public function advanceToStage(
        string $newStage,
        ?string $transitionedBy = 'system',
        ?string $notes = null
    ): FabricBatchStageLog {
        $now = now();

        // Close the current open stage log
        $currentLog = $this->activeStageLog()->first();
        if ($currentLog) {
            $durationMinutes = (int) $currentLog->entered_at->diffInMinutes($now);
            $slaHours        = ManufacturingStage::where('stage_name', $currentLog->stage_name)
                                                 ->value('sla_hours') ?? 36;
            $slaBreached     = $durationMinutes > ($slaHours * 60);

            $currentLog->update([
                'exited_at'        => $now,
                'duration_minutes' => $durationMinutes,
                'sla_breached'     => $slaBreached,
            ]);
        }

        // Resolve the new ManufacturingStage record
        $stage = ManufacturingStage::where('stage_name', $newStage)->firstOrFail();

        // Update the batch's denormalized current_stage and reset the timer
        $this->update([
            'current_stage'   => $newStage,
            'status'          => 'on_track',
            'stage_entered_at' => $now,
        ]);

        // Open a new log entry for the new stage
        return $this->stageLogs()->create([
            'manufacturing_stage_id' => $stage->id,
            'stage_name'             => $newStage,
            'entered_at'             => $now,
            'transitioned_by'        => $transitionedBy,
            'notes'                  => $notes,
        ]);
    }

    /**
     * Flag this batch as delayed in place (no stage change).
     */
    public function flagAsDelayed(): void
    {
        $this->update(['status' => 'delayed']);
    }

    /**
     * Returns true if this batch is currently stuck (on_track + overdue).
     */
    public function isOverdue(): bool
    {
        if (!$this->stage_entered_at) {
            return false;
        }

        $slaHours = ManufacturingStage::where('stage_name', $this->current_stage)
                                       ->value('sla_hours') ?? 36;

        return $this->stage_entered_at->addHours($slaHours)->isPast();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Active batches that have not been cancelled or completed.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Batches currently at a specific manufacturing stage.
     */
    public function scopeAtStage($query, string $stage)
    {
        return $query->where('current_stage', $stage);
    }

    /**
     * Batches already flagged as delayed.
     */
    public function scopeDelayed($query)
    {
        return $query->where('status', 'delayed');
    }

    /**
     * Batches stuck at Weaving or Dyeing beyond their SLA hours.
     * Used by the Phase 4 delay-detection worker.
     *
     * @param  int  $thresholdHours  Hours overdue (default 36)
     */
    public function scopeOverdueInWeavingOrDyeing($query, int $thresholdHours = 36)
    {
        $cutoff = now()->subHours($thresholdHours);

        return $query->active()
                     ->whereIn('current_stage', ['Weaving', 'Dyeing'])
                     ->where('status', 'on_track')          // not already flagged
                     ->where('stage_entered_at', '<=', $cutoff);
    }
}
