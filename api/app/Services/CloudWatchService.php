<?php

namespace App\Services;

use Aws\CloudWatch\CloudWatchClient;
use Illuminate\Support\Facades\Log;

/**
 * CloudWatchService
 *
 * Publishes custom metrics and structured log events to AWS CloudWatch.
 * Namespace: FabricFlow/Shipments
 */
class CloudWatchService
{
    private ?CloudWatchClient $client = null;
    private string $region;
    private string $namespace;

    public function __construct()
    {
        $this->region    = config('services.aws.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->namespace = 'FabricFlow/Shipments';
    }

    // ─── Metrics ───────────────────────────────────────────────────────────────

    /**
     * Report the current count of delayed shipments.
     * This feeds the CloudWatch alarm defined in cloudwatch.tf.
     */
    public function putDelayedShipmentsCount(int $count): void
    {
        $this->putMetric('DelayedShipments', $count, 'Count');
    }

    /**
     * Report total active shipments in pipeline.
     */
    public function putActiveShipmentsCount(int $count): void
    {
        $this->putMetric('ActiveShipments', $count, 'Count');
    }

    /**
     * Report a stage transition event (increment counter).
     */
    public function putStageTransition(string $stage): void
    {
        $this->putMetric('StageTransitions', 1, 'Count', [
            ['Name' => 'Stage', 'Value' => $stage],
        ]);
    }

    /**
     * Report API response latency for tracking endpoint.
     */
    public function putApiLatency(float $milliseconds): void
    {
        $this->putMetric('ApiLatencyMs', $milliseconds, 'Milliseconds');
    }

    // ─── Core ──────────────────────────────────────────────────────────────────

    private function putMetric(
        string $metricName,
        float $value,
        string $unit = 'None',
        array $dimensions = []
    ): void {
        if (!$this->isConfigured()) {
            Log::info("CloudWatch[{$this->namespace}/{$metricName}] = {$value} {$unit}");
            return;
        }

        try {
            $this->client()->putMetricData([
                'Namespace'  => $this->namespace,
                'MetricData' => [[
                    'MetricName' => $metricName,
                    'Value'      => $value,
                    'Unit'       => $unit,
                    'Timestamp'  => now()->toDateTimeLocalString(),
                    'Dimensions' => $dimensions,
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning("CloudWatchService metric failed: {$e->getMessage()}");
        }
    }

    private function client(): CloudWatchClient
    {
        if (!$this->client) {
            $this->client = new CloudWatchClient([
                'version' => 'latest',
                'region'  => $this->region,
            ]);
        }
        return $this->client;
    }

    private function isConfigured(): bool
    {
        // On EC2 with an IAM role, AWS_DEFAULT_REGION is enough.
        // Locally, we just log to console.
        return app()->environment('production') || env('AWS_ACCESS_KEY_ID');
    }
}
