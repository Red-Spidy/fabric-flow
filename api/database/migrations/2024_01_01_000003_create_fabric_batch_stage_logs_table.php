<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates `fabric_batch_stage_logs` — immutable audit log of every
     * stage transition for every batch. This is the time-series record
     * that drives the Industry 4.0 delay-detection worker (Phase 4).
     *
     * Design principles:
     *  - Append-only: rows are never updated, only inserted
     *  - A batch's CURRENT stage = the log row with the latest `entered_at`
     *    and a NULL `exited_at`
     *  - When a batch advances, the current open row is closed (exited_at set)
     *    and a new row is opened for the next stage
     */
    public function up(): void
    {
        Schema::create('fabric_batch_stage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The batch this log entry belongs to
            $table->uuid('fabric_batch_id')->index();

            // The stage this log entry records
            $table->uuid('manufacturing_stage_id')->index();

            // Denormalized stage name for fast queries without joins
            $table->string('stage_name', 64)->index();

            // When the batch entered this stage
            $table->timestamp('entered_at')->useCurrent()->index();

            // When the batch exited this stage (NULL = still active here)
            $table->timestamp('exited_at')->nullable()->index();

            // Duration in minutes (computed on exit for analytics)
            $table->unsignedInteger('duration_minutes')->nullable();

            // Was the SLA breached for this stage pass?
            $table->boolean('sla_breached')->default(false)->index();

            // Operator / system actor who triggered the transition
            $table->string('transitioned_by', 191)->nullable();   // user UUID or "system"

            // Free-text notes (quality checks, rejections, rework reasons)
            $table->text('notes')->nullable();

            // Optional: GPS or facility coordinates at time of scan/transition
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // FK to Fleetbase Order if this log entry coincides with a dispatch
            $table->string('fleetbase_order_uuid', 191)->nullable()->index();

            $table->timestamps();

            // Composite index: fast lookup of "all open logs per batch"
            $table->index(['fabric_batch_id', 'exited_at'], 'idx_batch_open_stage');

            // Composite index: delay-detection worker queries
            $table->index(['stage_name', 'entered_at', 'exited_at'], 'idx_delay_detection');

            // Foreign key constraints
            $table->foreign('fabric_batch_id')
                  ->references('id')
                  ->on('fabric_batches')
                  ->onDelete('cascade');

            $table->foreign('manufacturing_stage_id')
                  ->references('id')
                  ->on('manufacturing_stages')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabric_batch_stage_logs');
    }
};
