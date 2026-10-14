<?php

function run_ookla_speedtest(PDO $pdo, bool $scheduled = false): array
{
    $testType = $scheduled ? 'automatic' : 'manual';

    if (!function_exists('exec')) {
        throw new RuntimeException('Speedtest requires the PHP exec function, which is unavailable.');
    }

    $lock = $pdo->query("SELECT GET_LOCK('lantern_ookla_speedtest', 0)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new RuntimeException('A speed test is already running.');
    }

    try {
        if ($scheduled) {
            require_once __DIR__ . '/settings.php';
            $interval = get_app_setting($pdo, 'speedtest_interval_hours', '24');
            if (!in_array($interval, ['1', '3', '6', '12', '24'], true)) {
                return ['success' => true, 'skipped' => true, 'error' => null];
            }

            $lastRun = $pdo->query(
                'SELECT UNIX_TIMESTAMP(MAX(checked_at)) FROM speedtest_results'
            )->fetchColumn();
            $now = (int) $pdo->query('SELECT UNIX_TIMESTAMP(NOW())')->fetchColumn();
            if ($lastRun !== false && $lastRun !== null &&
                $now - (int) $lastRun < (int) $interval * 3600) {
                return ['success' => true, 'skipped' => true, 'error' => null];
            }
        }

        $versionOutput = [];
        $versionExitCode = 0;
        exec('speedtest --version 2>&1', $versionOutput, $versionExitCode);
        $versionText = implode(' ', $versionOutput);
        $isPythonSpeedtestCli = stripos($versionText, 'speedtest-cli') !== false
            || stripos($versionText, 'github.com/sivel/speedtest-cli') !== false;

        $output = [];
        $exitCode = 0;
        $command = $isPythonSpeedtestCli
            ? 'speedtest --json 2>&1'
            : 'speedtest --format=json --accept-license --accept-gdpr 2>&1';
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode(PHP_EOL, $output), true);
        $downloadBandwidth = $isPythonSpeedtestCli
            ? ($decoded['download'] ?? null)
            : ($decoded['download']['bandwidth'] ?? null);
        $uploadBandwidth = $isPythonSpeedtestCli
            ? ($decoded['upload'] ?? null)
            : ($decoded['upload']['bandwidth'] ?? null);
        $validResult = is_array($decoded)
            && is_numeric($downloadBandwidth)
            && is_numeric($uploadBandwidth);

        if ($exitCode !== 0 || !$validResult) {
            $error = $exitCode !== 0
                ? 'Speedtest CLI failed with exit code ' . $exitCode . '. Check that it is installed and available to the PHP service account.'
                : 'Speedtest CLI returned an unrecognized result.';
            $stmt = $pdo->prepare(
                'INSERT INTO speedtest_results (test_type, error_message) VALUES (?, ?)'
            );
            $stmt->execute([$testType, $error]);
            error_log($error . ' Version: ' . $versionText . ' Output: ' . implode(' ', $output));
            return [
                'success' => false,
                'id' => (int) $pdo->lastInsertId(),
                'error' => $error
            ];
        }

        $server = $decoded['server'] ?? [];
        $resultUrl = $isPythonSpeedtestCli
            ? ($decoded['share'] ?? null)
            : ($decoded['result']['url'] ?? null);
        $packetLoss = $decoded['packetLoss'] ?? null;
        $truncateServerField = static function (?string $value): ?string {
            if ($value === null || !preg_match('/^.{151}/us', $value)) {
                return $value;
            }
            return preg_replace('/^(.{0,150}).*$/us', '$1', $value);
        };
        $serverName = $truncateServerField(isset($server['name']) ? (string) $server['name'] : null);
        $serverLocationValue = $isPythonSpeedtestCli
            ? trim((string) ($server['country'] ?? ''))
            : (string) ($server['location'] ?? '');
        $serverLocation = $truncateServerField($serverLocationValue !== '' ? $serverLocationValue : null);
        $stmt = $pdo->prepare(
            'INSERT INTO speedtest_results '
            . '(test_type, download_mbps, upload_mbps, ping_ms, jitter_ms, packet_loss, server_name, server_location, result_url) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $testType,
            round(
                (float) $downloadBandwidth * ($isPythonSpeedtestCli ? 1 : 8) / 1000000,
                3
            ),
            round(
                (float) $uploadBandwidth * ($isPythonSpeedtestCli ? 1 : 8) / 1000000,
                3
            ),
            $isPythonSpeedtestCli
                ? (isset($decoded['ping']) && is_numeric($decoded['ping']) ? $decoded['ping'] : null)
                : (isset($decoded['ping']['latency']) && is_numeric($decoded['ping']['latency'])
                    ? $decoded['ping']['latency']
                    : null),
            $isPythonSpeedtestCli
                ? null
                : (isset($decoded['ping']['jitter']) && is_numeric($decoded['ping']['jitter'])
                    ? $decoded['ping']['jitter']
                    : null),
            is_numeric($packetLoss) ? $packetLoss : null,
            $serverName,
            $serverLocation,
            is_string($resultUrl) && filter_var($resultUrl, FILTER_VALIDATE_URL) ? $resultUrl : null
        ]);

        return ['success' => true, 'id' => (int) $pdo->lastInsertId(), 'error' => null];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('lantern_ookla_speedtest')");
    }
}
