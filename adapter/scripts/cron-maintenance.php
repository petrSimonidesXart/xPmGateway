#!/usr/bin/env php
<?php
/**
 * Cron maintenance script — run every minute.
 * Handles timeout detection and worker health monitoring independently of the worker process.
 *
 * Crontab entry (inside DDEV or on production):
 *   * * * * * php /path/to/adapter/scripts/cron-maintenance.php
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Model\Service\AlertService;
use App\Model\Service\JobService;
use App\Model\Service\WorkerStatusService;

$container = App\Bootstrap::boot()->createContainer();

$jobService = $container->getByType(JobService::class);
assert($jobService instanceof JobService);

$workerStatus = $container->getByType(WorkerStatusService::class);
assert($workerStatus instanceof WorkerStatusService);

$alertService = $container->getByType(AlertService::class);
assert($alertService instanceof AlertService);

// 1. Process timed-out jobs (mark as timeout, reset eligible for retry)
$timeouts = $jobService->processTimeouts();
if ($timeouts['timed_out'] > 0) {
	echo date('c') . " Timed out: {$timeouts['timed_out']}, reset for retry: {$timeouts['reset_for_retry']}\n";
}

// 2. Check worker health and send alert if offline
$status = $workerStatus->getStatus();
if ($status['status'] === 'offline') {
	$alertService->sendWorkerOfflineAlert($status['last_seen_at']);
}
