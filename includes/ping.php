<?php

function ping_host_result($host, $timeout = 0.5) {
    $host = trim((string) $host);

    if ($host === '') {
        return ['success' => false, 'latency_ms' => null];
    }

    if (!function_exists('exec')) {
        throw new RuntimeException('The PHP exec function is required for ping checks.');
    }

    $safeHost = escapeshellarg($host);

    // Windows vs Linux ping command
    $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        ? 'ping -n 1 -w ' . ((int) ($timeout * 1000)) . ' ' . $safeHost
        : 'ping -n -c 1 -W ' . escapeshellarg((string) $timeout) . ' ' . $safeHost;

    exec($cmd, $output, $result);

    $latency = null;
    if ($result === 0) {
        foreach ($output as $line) {
            if (preg_match('/\btime[=<]\s*(\d+(?:[.,]\d+)?)\s*ms\b/i', $line, $matches)) {
                $latency = (float) str_replace(',', '.', $matches[1]);
                break;
            }
        }
    }

    return ['success' => $result === 0, 'latency_ms' => $latency];
}

function ping_host($host, $timeout = 0.5) {
    return ping_host_result($host, $timeout)['success'];
}
