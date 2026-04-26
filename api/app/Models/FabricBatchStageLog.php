<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricBatchStageLog extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * Design intent: This is an APPEND-ONLY audit table.
     * Do not update rows other than to close them (set exited_at, duration_minutes, sla_breached).
     */
    protected $table = 'fabric_batch_stage_logs';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'fabric_batch_id',
        'manufacturing_stage_id',
        'stage_name',
        'entered_at',
        'exited_at',
        'duration_minutes',
        'sla_breached',
        'transitioned_by',
        'notes',
        'latitude',
        'longitude',
        'fleetbase_order_uuid',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'entered_at'       => 'datetime',
        'exited_at'        => 'datetime',
        'duration_minutes' => 'integer',
        'sla_breached'     => 'boolean',
        'latitude'         => 'float',
        'longitude'        => 'float',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The batch this log entry belongs to.
     */
    public function fabricBatch(): BelongsTo
    {
        return $this->belongsTo(FabricBatch::class, 'fabric_batch_id');
    }

    /**
     * The stage configuration this log entry references.
     */
    public function manufacturingStage(): BelongsTo
    {
        return $this->belongsTo(ManufacturingStage::class, 'manufacturing_stage_id');
    }

    // ─── Derived Attributes ───────────────────────────────────────────────────

    /**
     * True if the batch is still actively at this stage (not yet exited).
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->exited_at);
    }

    /**
     * How many minutes the batch has been at this stage so far.
     * Returns stored duration if closed, live-calculated if still open.
     */
    public function getElapsedMinutesAttribute(): int
    {
        if ($this->duration_minutes !== null) {
            return $this->duration_minutes;
        }

        return (int) $this->entered_at->diffInMinutes(now());
    }

    /**
     * How many hours the batch has been at this stage.
     */
    public function getElapsedHoursAttribute(): float
    {
        return round($this->elapsed_minutes / 60, 2);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only open (currently active) log entries.
     */
    public function scopeOpen($query)
    {
        return $query->whereNull('exited_at');
    }

    /**
     * Only closed (completed) log entries.
     */
    public function scopeClosed($query)
    {
        return $query->whereNotNull('exited_at');
    }

    /**
     * Entries where the SLA was breached.
     */
    public function scopeBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    /**
     * Open entries for Weaving or Dyeing that exceed the threshold.
     * Used directly by the Phase 4 delay-detection cron for targeted queries.
     *
     * @param  int  $thresholdHours
     */
    public function scopeOverdueAtRiskStages($query, int $thresholdHours = 36)
    {
        $cutoff = now()->subHours($thresholdHours);

        return $query->open()
                     ->whereIn('stage_name', [
                         ManufacturingStage::WEAVING,
                         ManufacturingStage::DYEING,
                     ])
                     ->where('entered_at', '<=', $cutoff);
    }
}
