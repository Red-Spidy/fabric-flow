<?php

use App\Http\Controllers\AwsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FabricBatchController;
use App\Http\Controllers\ManufacturingStageController;
use Illuminate\Support\Facades\Route;

Route::prefix('int/v1')->group(function () {

    // ─── Dashboard ─────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ─── Manufacturing Stages ───────────────────────────────────────────────
    Route::prefix('manufacturing-stages')->group(function () {
        Route::get('/',        [ManufacturingStageController::class, 'index']);
        Route::get('/summary', [ManufacturingStageController::class, 'summary']);
        Route::get('/{id}',    [ManufacturingStageController::class, 'show']);
    });

    // ─── Fabric Batches ─────────────────────────────────────────────────────
    Route::prefix('fabric-batches')->group(function () {
        Route::get('/',              [FabricBatchController::class, 'index']);
        Route::post('/',             [FabricBatchController::class, 'store']);
        Route::get('/{id}',          [FabricBatchController::class, 'show']);
        Route::patch('/{id}',        [FabricBatchController::class, 'update']);
        Route::delete('/{id}',       [FabricBatchController::class, 'destroy']);
        Route::post('/{id}/advance', [FabricBatchController::class, 'advance']);
        Route::get('/{id}/logs',     [FabricBatchController::class, 'logs']);
    });

    // ─── AWS Real-Time Cloud Management (Admin only in UI) ──────────────────
    Route::prefix('aws')->group(function () {

        // Validate credentials
        Route::post('/validate',    [AwsController::class, 'validateCredentials']);

        // EC2
        Route::get('/ec2/instances',              [AwsController::class, 'listInstances']);
        Route::post('/ec2/launch',                [AwsController::class, 'launchInstance']);
        Route::delete('/ec2/{instanceId}',        [AwsController::class, 'terminateInstance']);

        // S3
        Route::get('/s3/buckets',                 [AwsController::class, 'listBuckets']);
        Route::post('/s3/buckets',                [AwsController::class, 'createBucket']);
        Route::delete('/s3/buckets/{bucket}',     [AwsController::class, 'deleteBucket']);

        // SNS Notifications
        Route::get('/sns/topics',                 [AwsController::class, 'listSnsTopics']);
        Route::post('/sns/topics',                [AwsController::class, 'createSnsTopic']);
        Route::post('/sns/subscribe',             [AwsController::class, 'subscribeEmail']);
        Route::post('/sns/publish',               [AwsController::class, 'publishNotification']);

        // CloudWatch
        Route::get('/cloudwatch/alarms',          [AwsController::class, 'getMetrics']);
    });

    // ─── Direct Email Integration ───────────────────────────────────────────
    Route::post('/email/send', [\App\Http\Controllers\EmailController::class, 'send']);
});
