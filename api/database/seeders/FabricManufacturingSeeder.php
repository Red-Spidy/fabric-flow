<?php

namespace Database\Seeders;

use App\Models\FabricBatch;
use App\Models\FabricBatchStageLog;
use App\Models\ManufacturingStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FabricManufacturingSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Seed Manufacturing Stages ─────────────────────────────────────
        $stagesConfig = [
            [
                'stage_name'        => 'Processing',
                'display_name'      => 'Raw Processing',
                'description'       => 'Initial cleaning, combing, and preparation of raw fibers.',
                'sequence_order'    => 1,
                'sla_hours'         => 24,
                'facility_name'     => 'Processing Unit A',
                'facility_location' => 'Surat, Gujarat',
                'responsible_team'  => 'Pre-Processing Team',
                'is_final_stage'    => false,
                'allows_rework'     => true,
            ],
            [
                'stage_name'        => 'Weaving',
                'display_name'      => 'Fabric Weaving',
                'description'       => 'Interlacing threads on mechanical and automated looms.',
                'sequence_order'    => 2,
                'sla_hours'         => 36,
                'facility_name'     => 'Weaving Floor B',
                'facility_location' => 'Surat, Gujarat',
                'responsible_team'  => 'Loom Operators',
                'is_final_stage'    => false,
                'allows_rework'     => false,
            ],
            [
                'stage_name'        => 'Dyeing',
                'display_name'      => 'Dyeing & Finishing',
                'description'       => 'Color application, fixation, and surface finishing treatments.',
                'sequence_order'    => 3,
                'sla_hours'         => 36,
                'facility_name'     => 'Dyehouse C',
                'facility_location' => 'Ahmedabad, Gujarat',
                'responsible_team'  => 'Dyeing Specialists',
                'is_final_stage'    => false,
                'allows_rework'     => true,
            ],
            [
                'stage_name'        => 'Warehouse',
                'display_name'      => 'Warehouse & QC',
                'description'       => 'Quality inspection, folding, packaging, and storage.',
                'sequence_order'    => 4,
                'sla_hours'         => 48,
                'facility_name'     => 'Central Warehouse',
                'facility_location' => 'Mumbai, Maharashtra',
                'responsible_team'  => 'QC & Logistics',
                'is_final_stage'    => false,
                'allows_rework'     => false,
            ],
            [
                'stage_name'        => 'Shipped',
                'display_name'      => 'Shipped to Buyer',
                'description'       => 'Dispatched to buyer — order fulfilled.',
                'sequence_order'    => 5,
                'sla_hours'         => 72,
                'facility_name'     => 'N/A',
                'facility_location' => 'In Transit',
                'responsible_team'  => 'Dispatch',
                'is_final_stage'    => true,
                'allows_rework'     => false,
            ],
        ];

        $stageModels = [];
        foreach ($stagesConfig as $cfg) {
            $stageModels[$cfg['stage_name']] = ManufacturingStage::firstOrCreate(
                ['stage_name' => $cfg['stage_name']],
                $cfg
            );
        }

        // ─── 2. Seed Fabric Batches ───────────────────────────────────────────
        $batches = [
            // --- Active / on-track ---
            ['batch_id' => 'FB-2024-00101', 'fabric_type' => 'cotton',    'quantity_kg' => 500.0,   'origin' => 'Surat, Gujarat',     'current_stage' => 'Processing', 'status' => 'on_track',  'supplier_name' => 'Gujarat Cotton Co.',   'hours_ago' => 10],
            ['batch_id' => 'FB-2024-00102', 'fabric_type' => 'silk',      'quantity_kg' => 120.5,   'origin' => 'Varanasi, UP',       'current_stage' => 'Weaving',    'status' => 'on_track',  'supplier_name' => 'Benares Silk Mills',   'hours_ago' => 20],
            ['batch_id' => 'FB-2024-00103', 'fabric_type' => 'polyester', 'quantity_kg' => 1200.0,  'origin' => 'Tirupur, TN',        'current_stage' => 'Dyeing',     'status' => 'on_track',  'supplier_name' => 'South India Synthetics','hours_ago' => 30],
            ['batch_id' => 'FB-2024-00104', 'fabric_type' => 'linen',     'quantity_kg' => 300.75,  'origin' => 'Kolkata, WB',        'current_stage' => 'Warehouse',  'status' => 'on_track',  'supplier_name' => 'Bengal Linen House',   'hours_ago' => 12],

            // --- Delayed (SLA breached) ---
            ['batch_id' => 'FB-2024-00105', 'fabric_type' => 'cotton',    'quantity_kg' => 800.0,   'origin' => 'Surat, Gujarat',     'current_stage' => 'Weaving',    'status' => 'delayed',   'supplier_name' => 'Gujarat Cotton Co.',   'hours_ago' => 50],
            ['batch_id' => 'FB-2024-00106', 'fabric_type' => 'wool',      'quantity_kg' => 60.25,   'origin' => 'Ludhiana, Punjab',   'current_stage' => 'Dyeing',     'status' => 'delayed',   'supplier_name' => 'Punjab Wool Traders',  'hours_ago' => 45],

            // --- Completed ---
            ['batch_id' => 'FB-2024-00107', 'fabric_type' => 'cotton',    'quantity_kg' => 400.0,   'origin' => 'Coimbatore, TN',     'current_stage' => 'Shipped',    'status' => 'completed', 'supplier_name' => 'Tamil Nadu Textiles',  'hours_ago' => 0],
            ['batch_id' => 'FB-2024-00108', 'fabric_type' => 'polyester', 'quantity_kg' => 950.0,   'origin' => 'Tirupur, TN',        'current_stage' => 'Shipped',    'status' => 'completed', 'supplier_name' => 'South India Synthetics','hours_ago' => 0],

            // --- Mixed Progress ---
            ['batch_id' => 'FB-2024-00109', 'fabric_type' => 'silk',      'quantity_kg' => 85.0,    'origin' => 'Varanasi, UP',       'current_stage' => 'Processing', 'status' => 'on_track',  'supplier_name' => 'Benares Silk Mills',   'hours_ago' => 5],
            ['batch_id' => 'FB-2024-00110', 'fabric_type' => 'linen',     'quantity_kg' => 210.0,   'origin' => 'Kolkata, WB',        'current_stage' => 'Warehouse',  'status' => 'on_track',  'supplier_name' => 'Bengal Linen House',   'hours_ago' => 8],
        ];

        foreach ($batches as $data) {
            $hoursAgo  = $data['hours_ago'];
            $enteredAt = $hoursAgo > 0 ? now()->subHours($hoursAgo) : now()->subHours(2);

            unset($data['hours_ago']);
            $data['stage_entered_at']         = $enteredAt;
            $data['expected_completion_date']  = now()->addDays(7)->toDateString();

            $batch = FabricBatch::firstOrCreate(
                ['batch_id' => $data['batch_id']],
                $data
            );

            // Only create log if none exist yet
            if ($batch->stageLogs()->count() === 0) {
                $stageModel = $stageModels[$data['current_stage']] ?? null;
                if ($stageModel) {
                    FabricBatchStageLog::create([
                        'fabric_batch_id'        => $batch->id,
                        'manufacturing_stage_id' => $stageModel->id,
                        'stage_name'             => $data['current_stage'],
                        'entered_at'             => $enteredAt,
                        'transitioned_by'        => 'system',
                        'notes'                  => 'Initial seeded stage log.',
                        'sla_breached'           => $data['status'] === 'delayed',
                    ]);
                }
            }
        }

        $this->command->info('✅ Manufacturing stages & batch data seeded successfully.');
    }
}
