<?php

function ping_host($host, $timeout = 0.5) {
    $host = trim((string) $host);

    if ($host === '') {
        return false;
    }

    $safeHost = escapeshellarg($host);

    // Windows vs Linux ping command
    $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        ? 'ping -n 1 -w ' . ((int) ($timeout * 1000)) . ' ' . $safeHost
        : 'ping -c 1 -W ' . escapeshellarg((string) $timeout) . ' ' . $safeHost;

    exec($cmd, $output, $result);

    // $result === 0 means success
    return $result === 0;
}
