<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/speedtest.php';

$pdo->exec('DELETE FROM speedtest_results WHERE checked_at < NOW() - INTERVAL 90 DAY');

$interval = get_app_setting($pdo, 'speedtest_interval_hours', '24');
if (!in_array($interval, ['0', '1', '3', '6', '12', '24'], true)) {
    fwrite(STDERR, 'Invalid scheduled speed test interval setting.' . PHP_EOL);
    exit(1);
}
if ($interval === '0') {
    exit;
}

try {
    $result = run_ookla_speedtest($pdo, true);
    if (!empty($result['skipped'])) {
        exit;
    }
    if (!$result['success']) {
        fwrite(STDERR, $result['error'] . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, 'Speed test completed.' . PHP_EOL);
} catch (Throwable $exception) {
    error_log('Scheduled Ookla Speedtest failed: ' . $exception->getMessage());
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
