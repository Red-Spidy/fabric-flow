<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates `manufacturing_stages` — lookup/config table defining each
     * production stage in the textile pipeline with SLA thresholds.
     *
     * Domain mapping: Fleetbase's generic Waypoint/Route -> ManufacturingStage
     *
     * Note: This is a reference/configuration table. Actual batch-to-stage
     * assignment lives in fabric_batch_stage_logs (the audit log).
     */
    public function up(): void
    {
        Schema::create('manufacturing_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Unique machine-readable stage key, e.g. "Weaving"
            $table->string('stage_name', 64)->unique()->index();

            // Human label, e.g. "Weaving & Loom Processing"
            $table->string('display_name', 128);

            // Optional description of what happens at this stage
            $table->text('description')->nullable();

            // The sequence position in the pipeline (1 = first, 4 = last before shipment)
            $table->unsignedTinyInteger('sequence_order')->unique();

            // Maximum acceptable hours a batch should remain at this stage
            // before being automatically flagged as "delayed"
            // Default 36 hours as specified in Phase 4 requirement
            $table->unsignedSmallInteger('sla_hours')->default(36);

            // Physical location / facility identifiers
            $table->string('facility_name', 255)->nullable();
            $table->string('facility_location', 255)->nullable();

            // Responsible department or team
            $table->string('responsible_team', 128)->nullable();

            // Whether this is the terminal/final stage before outbound shipment
            $table->boolean('is_final_stage')->default(false);

            // Whether batches can be routed back to this stage (rework loops)
            $table->boolean('allows_rework')->default(false);

            // FK to Fleetbase Place UUID (optional geo-anchor for the stage location)
            $table->string('fleetbase_place_uuid', 191)->nullable()->index();

            $table->timestamps();
        });

        // Seed the 4 canonical manufacturing stages immediately after table creation
        DB::table('manufacturing_stages')->insert([
            [
                'id'               => \Illuminate\Support\Str::uuid(),
                'stage_name'       => 'Processing',
                'display_name'     => 'Raw Material Processing',
                'description'      => 'Initial inspection, cleaning, and preparation of raw fabric materials.',
                'sequence_order'   => 1,
                'sla_hours'        => 24,
                'facility_name'    => 'Processing Unit',
                'is_final_stage'   => false,
                'allows_rework'    => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => \Illuminate\Support\Str::uuid(),
                'stage_name'       => 'Weaving',
                'display_name'     => 'Weaving & Loom Processing',
                'description'      => 'Conversion of yarn into fabric using industrial looms.',
                'sequence_order'   => 2,
                'sla_hours'        => 36,
                'facility_name'    => 'Weaving Hall',
                'is_final_stage'   => false,
                'allows_rework'    => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => \Illuminate\Support\Str::uuid(),
                'stage_name'       => 'Dyeing',
                'display_name'     => 'Dyeing & Finishing',
                'description'      => 'Fabric coloring, chemical treatment, and surface finishing.',
                'sequence_order'   => 3,
                'sla_hours'        => 36,
                'facility_name'    => 'Dye House',
                'is_final_stage'   => false,
                'allows_rework'    => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => \Illuminate\Support\Str::uuid(),
                'stage_name'       => 'Warehouse',
                'display_name'     => 'Finished Goods Warehouse',
                'description'      => 'Quality-passed fabric stored and staged for outbound shipment to distributors.',
                'sequence_order'   => 4,
                'sla_hours'        => 72,
                'facility_name'    => 'Central Warehouse',
                'is_final_stage'   => true,
                'allows_rework'    => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_stages');
    }
};
