<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ping.php';

$devices = $pdo->query(
    "SELECT id, ip, hostname FROM devices ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$insert = $pdo->prepare(
    'INSERT INTO ping_results (device_id, success, latency_ms) VALUES (?, ?, ?)'
);

foreach ($devices as $device) {
    $target = trim((string) ($device['ip'] ?? ''));
    if ($target === '') {
        $target = trim((string) ($device['hostname'] ?? ''));
    }

    if ($target === '') {
        continue;
    }

    $result = ping_host_result($target, 1);
    $insert->execute([
        (int) $device['id'],
        $result['success'] ? 1 : 0,
        $result['latency_ms']
    ]);
}

$pdo->exec('DELETE FROM ping_results WHERE checked_at < NOW() - INTERVAL 30 DAY');
