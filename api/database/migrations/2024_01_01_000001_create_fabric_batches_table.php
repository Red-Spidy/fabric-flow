<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the `fabric_batches` table, the core domain entity for
     * tracking raw fabric inventory through the manufacturing pipeline.
     *
     * Domain mapping: Fleetbase's generic Payload/Item -> FabricBatch
     */
    public function up(): void
    {
        Schema::create('fabric_batches', function (Blueprint $table) {
            // Primary key — UUID for distributed system safety
            $table->uuid('id')->primary();

            // Human-readable business identifier, e.g. "FB-2024-00142"
            $table->string('batch_id', 64)->unique()->index();

            // Fabric material type — enforced at DB level for data integrity
            $table->enum('fabric_type', ['cotton', 'silk', 'linen', 'polyester', 'wool'])
                  ->default('cotton')
                  ->index();

            // Weight of the batch in kilograms
            $table->decimal('quantity_kg', 10, 3)->unsigned();

            // Geographic / supplier origin, e.g. "Surat, Gujarat"
            $table->string('origin', 255);

            // Current stage in the manufacturing pipeline
            // Maps to ManufacturingStage.stage_name for denormalized fast reads
            $table->enum('current_stage', [
                'Processing',
                'Weaving',
                'Dyeing',
                'Warehouse',
                'Shipped',
            ])->default('Processing')->index();

            // Pipeline status — "on_track", "delayed", "completed", "cancelled"
            $table->enum('status', [
                'on_track',
                'delayed',
                'completed',
                'cancelled',
            ])->default('on_track')->index();

            // Timestamp when batch entered its current stage (for delay detection)
            $table->timestamp('stage_entered_at')->nullable()->index();

            // FK to the related Fleetbase Order (outbound logistics from Warehouse)
            // Nullable because batches only get a shipment order at the Warehouse stage
            $table->string('fleetbase_order_uuid', 191)->nullable()->index();

            // Optional FK to a Fleetbase Company (multi-tenant support)
            $table->string('company_uuid', 191)->nullable()->index();

            // Supplier / vendor reference
            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_contact', 255)->nullable();

            // Optional: target completion date
            $table->date('expected_completion_date')->nullable();

            // Soft-deletes for audit trail compliance
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabric_batches');
    }
};
