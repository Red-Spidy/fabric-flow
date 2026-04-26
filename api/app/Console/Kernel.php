<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\DetectShipmentDelays;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ── Delay Detection (every 30 minutes) ───────────────────────────────
        // Scans for batches stuck at Weaving/Dyeing beyond SLA hours,
        // flags them 'delayed', pushes S3 alert log + CloudWatch metric.
        $schedule->command('shipments:detect-delays')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/delay-detection.log'));

        // ── Daily Backup of batch data to S3 ────────────────────────────────
        $schedule->command('db:seed --class=FabricManufacturingSeeder', ['--no-interaction'])
                 ->dailyAt('03:00')
                 ->environments(['local']); // only re-seed in local dev
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
