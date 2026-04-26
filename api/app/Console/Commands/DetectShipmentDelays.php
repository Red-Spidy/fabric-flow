<?php

namespace App\Console\Commands;

use App\Models\FabricBatch;
use App\Services\S3LogService;
use App\Services\CloudWatchService;
use App\Mail\ShipmentDelayedEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * DetectShipmentDelays
 *
 * Cron command run every 30 minutes by go-crond (scheduler container).
 * Workflow:
 *  1. Find all on_track batches that have exceeded their SLA at Weaving/Dyeing
 *  2. Flag them as 'delayed' in the database
 *  3. Write a delay-alert JSON to S3
 *  4. Push DelayedShipments count metric to CloudWatch
 *     → CloudWatch alarm fires → SNS email alert to admin
 */
class DetectShipmentDelays extends Command
{
    protected $signature   = 'shipments:detect-delays {--dry-run : Simulate without DB changes}';
    protected $description = 'Detect overdue shipments, flag them as delayed, and push alerts to S3 + CloudWatch';

    public function __construct(
        private S3LogService      $s3,
        private CloudWatchService $cloudwatch
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startedAt = now();
        $dryRun    = $this->option('dry-run');

        $this->info("🔍 Detecting shipment delays at {$startedAt->toDateTimeString()}");

        // ── 1. Find overdue batches ───────────────────────────────────────────
        $overdueBatches = FabricBatch::overdueInWeavingOrDyeing()->get();
        $count = $overdueBatches->count();

        $this->info("  Found {$count} overdue batch(es)");

        if ($count === 0) {
            $this->cloudwatch->putDelayedShipmentsCount(0);
            return self::SUCCESS;
        }

        // ── 2. Flag each batch + log to S3 ───────────────────────────────────
        $totalDelayed = 0;

        foreach ($overdueBatches as $batch) {
            /** @var FabricBatch $batch */
            $hoursOverdue = $batch->stage_entered_at
                ? now()->diffInMinutes($batch->stage_entered_at) / 60
                : 0;

            $this->warn(sprintf(
                '  ⚠️  %s | Stage: %s | %.1f hrs overdue',
                $batch->batch_id,
                $batch->current_stage,
                $hoursOverdue
            ));

            if (!$dryRun) {
                // Flag as delayed in DB
                $batch->flagAsDelayed();

                // Write alert to S3
                $this->s3->logDelayAlert(
                    $batch->batch_id,
                    $batch->current_stage,
                    $hoursOverdue
                );

                // Send direct email to buyer via AWS SES (if email is set)
                if ($batch->buyer_email) {
                    try {
                        Mail::to($batch->buyer_email)->send(new ShipmentDelayedEmail($batch, $hoursOverdue));
                        $this->info("    📧 Email sent to {$batch->buyer_email}");
                    } catch (\Exception $e) {
                        Log::error("Failed to send delay email for batch {$batch->batch_id}: " . $e->getMessage());
                    }
                }
            }

            $totalDelayed++;
        }

        // ── 3. Push metric to CloudWatch ──────────────────────────────────────
        if (!$dryRun) {
            $this->cloudwatch->putDelayedShipmentsCount($totalDelayed);

            Log::channel('daily')->warning("Delay detection: {$totalDelayed} shipments flagged", [
                'batch_ids' => $overdueBatches->pluck('batch_id')->toArray(),
                'run_at'    => $startedAt->toIso8601String(),
            ]);
        }

        // ── 4. Also push total active count for the dashboard metric ─────────
        $activeCount = FabricBatch::active()->count();
        $this->cloudwatch->putActiveShipmentsCount($activeCount);

        $duration = now()->diffInMilliseconds($startedAt);
        $this->info("✅ Done in {$duration}ms. Flagged: {$totalDelayed}, Active: {$activeCount}");

        return self::SUCCESS;
    }
}
