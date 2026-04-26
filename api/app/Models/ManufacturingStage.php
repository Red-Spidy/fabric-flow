<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingStage extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'manufacturing_stages';

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
        'stage_name',
        'display_name',
        'description',
        'sequence_order',
        'sla_hours',
        'facility_name',
        'facility_location',
        'responsible_team',
        'is_final_stage',
        'allows_rework',
        'fleetbase_place_uuid',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sequence_order' => 'integer',
        'sla_hours'      => 'integer',
        'is_final_stage' => 'boolean',
        'allows_rework'  => 'boolean',
    ];

    /**
     * Pre-defined canonical stage names.
     */
    public const PROCESSING = 'Processing';
    public const WEAVING    = 'Weaving';
    public const DYEING     = 'Dyeing';
    public const WAREHOUSE  = 'Warehouse';

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * All stage log entries that reference this stage.
     */
    public function stageLogs(): HasMany
    {
        return $this->hasMany(FabricBatchStageLog::class, 'manufacturing_stage_id');
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Retrieve the next stage in the pipeline sequence.
     */
    public function nextStage(): ?ManufacturingStage
    {
        return static::where('sequence_order', $this->sequence_order + 1)->first();
    }

    /**
     * Retrieve the previous stage in the pipeline sequence.
     */
    public function previousStage(): ?ManufacturingStage
    {
        return static::where('sequence_order', $this->sequence_order - 1)->first();
    }

    /**
     * SLA deadline as a Carbon instance relative to a given start time.
     *
     * @param  \Carbon\Carbon|null  $from  Defaults to now()
     */
    public function slaDeadline(?\Carbon\Carbon $from = null): \Carbon\Carbon
    {
        return ($from ?? now())->addHours($this->sla_hours);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Stages ordered by their pipeline sequence.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence_order');
    }

    /**
     * Only stages that have a delay-risk (Weaving and Dyeing per domain spec).
     */
    public function scopeDelayRiskStages($query)
    {
        return $query->whereIn('stage_name', [self::WEAVING, self::DYEING]);
    }
}
