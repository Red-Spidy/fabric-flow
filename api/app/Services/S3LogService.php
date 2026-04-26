<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

/**
 * S3LogService
 *
 * Pushes structured tracking events and alerts to the AWS S3 log bucket.
 * Every event is stored as a JSON object under a date-partitioned prefix:
 *   s3://{bucket}/tracking/{YYYY}/{MM}/{DD}/{timestamp}-{type}.json
 */
class S3LogService
{
    private ?S3Client $client = null;
    private string $bucket;
    private string $region;

    public function __construct()
    {
        $this->bucket = config('services.aws.s3_log_bucket', env('AWS_S3_LOG_BUCKET', ''));
        $this->region = config('services.aws.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
    }

    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Log a shipment stage transition event.
     */
    public function logStageTransition(
        string $batchId,
        string $fromStage,
        string $toStage,
        string $transitionedBy = 'system',
        ?string $notes = null
    ): void {
        $this->push('stage-transition', [
            'batch_id'         => $batchId,
            'from_stage'       => $fromStage,
            'to_stage'         => $toStage,
            'transitioned_by'  => $transitionedBy,
            'notes'            => $notes,
        ]);
    }

    /**
     * Log a shipment delay alert.
     */
    public function logDelayAlert(string $batchId, string $stage, float $hoursOverdue): void
    {
        $this->push('delay-alert', [
            'batch_id'       => $batchId,
            'stage'          => $stage,
            'hours_overdue'  => round($hoursOverdue, 2),
            'severity'       => $hoursOverdue > 72 ? 'critical' : 'warning',
        ]);
    }

    /**
     * Log a new shipment creation.
     */
    public function logShipmentCreated(string $batchId, array $payload): void
    {
        $this->push('shipment-created', array_merge(
            ['batch_id' => $batchId],
            $payload
        ));
    }

    /**
     * Log a generic dashboard action.
     */
    public function logAction(string $action, array $data = []): void
    {
        $this->push($action, $data);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function push(string $type, array $payload): void
    {
        if (!$this->bucket) {
            // S3 not configured — log locally only
            Log::channel('daily')->info("S3Log[{$type}]", $payload);
            return;
        }

        try {
            $now  = now();
            $body = json_encode([
                'type'       => $type,
                'timestamp'  => $now->toIso8601String(),
                'host'       => gethostname(),
                'payload'    => $payload,
            ], JSON_PRETTY_PRINT);

            $key = sprintf(
                'tracking/%s/%s/%s/%s-%s.json',
                $now->format('Y'),
                $now->format('m'),
                $now->format('d'),
                $now->format('His'),
                $type
            );

            $this->client()->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $key,
                'Body'        => $body,
                'ContentType' => 'application/json',
            ]);

            Log::info("S3Log written: s3://{$this->bucket}/{$key}");
        } catch (\Throwable $e) {
            // Never let S3 errors break the main request flow
            Log::warning("S3LogService failed: {$e->getMessage()}");
        }
    }

    private function client(): S3Client
    {
        if (!$this->client) {
            $this->client = new S3Client([
                'version' => 'latest',
                'region'  => $this->region,
                // On EC2 with IAM role — credentials are picked up automatically.
                // For local dev, set AWS_ACCESS_KEY_ID + AWS_SECRET_ACCESS_KEY in .env
            ]);
        }
        return $this->client;
    }
}
