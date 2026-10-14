<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/speedtest.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$csrfToken = $isAdmin ? csrf_token() : '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Only administrators can run a speed test.');
    }
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    set_time_limit(180);
    try {
        $run = run_ookla_speedtest($pdo);
        if ($run['success']) {
            $message = 'Speed test completed.';
        } else {
            $error = $run['error'];
        }
    } catch (Throwable $exception) {
        error_log('Manual Ookla Speedtest failed: ' . $exception->getMessage());
        $error = $exception->getMessage();
    }
}

$historyStmt = $pdo->query("
    SELECT UNIX_TIMESTAMP(checked_at) AS checked_timestamp, test_type, download_mbps, upload_mbps,
           ping_ms, jitter_ms, packet_loss, server_name, server_location, result_url, error_message
    FROM speedtest_results
    WHERE checked_at >= NOW() - INTERVAL 30 DAY
    ORDER BY checked_at DESC
");
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
$chartHistory = array_reverse($history);
$latestSuccess = null;
foreach ($history as $result) {
    if ($result['error_message'] === null) {
        $latestSuccess = $result;
        break;
    }
}

ob_start();
?>
<div class="dw-speedtest-page">
<h1>Internet Speed Test</h1>
<p>Tests use Ookla Speedtest and measure the internet connection from the LANtern server.</p>

<?php if ($message !== ''): ?>
    <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <form method="post" class="dw-speedtest-run-form" id="dw-speedtest-run-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="dw-btn dw-btn-primary" id="dw-speedtest-run-button">Run Speed Test Now</button>
        <span>Tests may take up to a minute and use significant bandwidth.</span>
    </form>
    <div class="dw-speedtest-progress" id="dw-speedtest-progress" role="status" aria-live="polite" hidden>
        <span class="dw-speedtest-spinner" aria-hidden="true"></span>
        <span>Speed test in progress. This can take up to a minute; please keep this page open.</span>
    </div>
<?php endif; ?>

<?php if ($latestSuccess !== null): ?>
    <section class="dw-panel dw-speedtest-latest">
        <h2>Latest Successful Test</h2>
        <div class="dw-speedtest-stats">
            <div><strong><?= htmlspecialchars((string) $latestSuccess['download_mbps'], ENT_QUOTES, 'UTF-8'); ?></strong><span>Download (Mbps)</span></div>
            <div><strong><?= htmlspecialchars((string) $latestSuccess['upload_mbps'], ENT_QUOTES, 'UTF-8'); ?></strong><span>Upload (Mbps)</span></div>
            <div><strong><?= htmlspecialchars((string) $latestSuccess['ping_ms'], ENT_QUOTES, 'UTF-8'); ?></strong><span>Ping (ms)</span></div>
            <?php if ($latestSuccess['jitter_ms'] !== null): ?>
                <div><strong><?= htmlspecialchars((string) $latestSuccess['jitter_ms'], ENT_QUOTES, 'UTF-8'); ?></strong><span>Jitter (ms)</span></div>
            <?php endif; ?>
        </div>
        <p>
            <?= date('Y-m-d H:i', (int) $latestSuccess['checked_timestamp']); ?>
            <?php if ($latestSuccess['server_name']): ?>
                · <?= htmlspecialchars((string) $latestSuccess['server_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?= $latestSuccess['server_location']
                    ? '— ' . htmlspecialchars((string) $latestSuccess['server_location'], ENT_QUOTES, 'UTF-8')
                    : ''; ?>
            <?php endif; ?>
            <?php if ($latestSuccess['result_url']): ?>
                · <a href="<?= htmlspecialchars((string) $latestSuccess['result_url'], ENT_QUOTES, 'UTF-8'); ?>"
                     target="_blank" rel="noopener noreferrer">Ookla result</a>
            <?php endif; ?>
        </p>
    </section>
<?php elseif (!$history): ?>
    <p>No speed test results yet. Run a manual test or enable scheduled testing in Admin Settings.</p>
<?php endif; ?>

<h2>Speed History (Last 30 Days)</h2>
<?php if (!$chartHistory): ?>
    <p>The history graph will appear after the first test.</p>
<?php else: ?>
    <?php
    $chartWidth = 960;
    $chartHeight = 320;
    $chartLeft = 58;
    $chartRight = 18;
    $chartTop = 20;
    $chartBottom = 42;
    $plotWidth = $chartWidth - $chartLeft - $chartRight;
    $plotHeight = $chartHeight - $chartTop - $chartBottom;
    $windowStart = time() - 30 * 86400;
    $maxSpeed = 0.0;
    foreach ($chartHistory as $sample) {
        if ($sample['download_mbps'] !== null) {
            $maxSpeed = max($maxSpeed, (float) $sample['download_mbps']);
        }
        if ($sample['upload_mbps'] !== null) {
            $maxSpeed = max($maxSpeed, (float) $sample['upload_mbps']);
        }
    }
    $speedScale = max(10, ceil($maxSpeed / 10) * 10);
    ?>
    <div class="dw-speedtest-chart-section">
    <svg class="dw-speedtest-chart" viewBox="0 0 <?= $chartWidth; ?> <?= $chartHeight; ?>"
         role="img" aria-label="Download and upload speed in megabits per second over the last 30 days.">
        <?php for ($tick = 0; $tick <= 4; $tick++): ?>
            <?php
            $y = $chartTop + ($plotHeight * $tick / 4);
            $tickValue = $speedScale * (4 - $tick) / 4;
            ?>
            <line x1="<?= $chartLeft; ?>" y1="<?= $y; ?>" x2="<?= $chartWidth - $chartRight; ?>"
                  y2="<?= $y; ?>" class="dw-speedtest-gridline" />
            <text x="<?= $chartLeft - 8; ?>" y="<?= $y + 4; ?>" text-anchor="end"
                  class="dw-speedtest-axis"><?= htmlspecialchars((string) round($tickValue), ENT_QUOTES, 'UTF-8'); ?></text>
        <?php endfor; ?>
        <?php for ($tick = 0; $tick <= 4; $tick++): ?>
            <?php
            $x = $chartLeft + ($plotWidth * $tick / 4);
            $labelTime = $windowStart + (30 * 86400 * $tick / 4);
            ?>
            <text x="<?= $x; ?>" y="<?= $chartHeight - 12; ?>" text-anchor="middle"
                  class="dw-speedtest-axis"><?= date('M j', (int) $labelTime); ?></text>
        <?php endfor; ?>
        <text x="15" y="<?= $chartTop + ($plotHeight / 2); ?>" text-anchor="middle"
              class="dw-speedtest-axis" transform="rotate(-90 15 <?= $chartTop + ($plotHeight / 2); ?>)">Speed (Mbps)</text>
        <?php foreach (['download_mbps' => 'dw-speedtest-download', 'upload_mbps' => 'dw-speedtest-upload'] as $metric => $className): ?>
            <?php
            $segment = [];
            $segments = [];
            foreach ($chartHistory as $sample) {
                if ($sample['error_message'] !== null || $sample[$metric] === null) {
                    if ($segment) {
                        $segments[] = $segment;
                        $segment = [];
                    }
                    continue;
                }
                $segment[] = [
                    'x' => $chartLeft + (
                        ((int) $sample['checked_timestamp'] - $windowStart) / (30 * 86400) * $plotWidth
                    ),
                    'y' => $chartTop + (
                        1 - min((float) $sample[$metric], $speedScale) / $speedScale
                    ) * $plotHeight,
                    'sample' => $sample
                ];
            }
            if ($segment) {
                $segments[] = $segment;
            }
            ?>
            <?php foreach ($segments as $points): ?>
                <?php if (count($points) > 1): ?>
                    <polyline class="<?= $className; ?>" points="<?=
                        htmlspecialchars(implode(' ', array_map(static function ($point) {
                            return $point['x'] . ',' . $point['y'];
                        }, $points)), ENT_QUOTES, 'UTF-8');
                    ?>" />
                <?php endif; ?>
                <?php foreach ($points as $point): ?>
                    <circle class="<?= $className; ?>" cx="<?= $point['x']; ?>" cy="<?= $point['y']; ?>" r="3">
                        <title><?= date('Y-m-d H:i', (int) $point['sample']['checked_timestamp']); ?> ·
                            <?= htmlspecialchars((string) $point['sample'][$metric], ENT_QUOTES, 'UTF-8'); ?> Mbps</title>
                    </circle>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php foreach ($chartHistory as $sample): ?>
            <?php if ($sample['error_message'] !== null): ?>
                <?php
                $failureX = $chartLeft + (
                    ((int) $sample['checked_timestamp'] - $windowStart) / (30 * 86400) * $plotWidth
                );
                $failureY = $chartTop + $plotHeight - 5;
                ?>
                <path class="dw-speedtest-failure"
                      d="M <?= $failureX - 4; ?> <?= $failureY - 4; ?> L <?= $failureX + 4; ?> <?= $failureY + 4; ?> M <?= $failureX + 4; ?> <?= $failureY - 4; ?> L <?= $failureX - 4; ?> <?= $failureY + 4; ?>">
                    <title><?= date('Y-m-d H:i', (int) $sample['checked_timestamp']); ?> · Test failed</title>
                </path>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <p class="dw-speedtest-legend">
        <span class="dw-speedtest-legend-download"></span> Download
        <span class="dw-speedtest-legend-upload"></span> Upload
        <span class="dw-speedtest-legend-failure"></span> Failed test
    </p>
    </div>
<?php endif; ?>

<?php if ($history): ?>
    <section class="dw-speedtest-recent">
        <h2>Recent Tests</h2>
        <div class="dw-table-scroll">
        <table class="dw-table dw-speedtest-table">
            <thead>
                <tr><th>Time</th><th>Type</th><th>Download</th><th>Upload</th><th>Ping</th><th>Server / Status</th></tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($history, 0, 10) as $result): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i', (int) $result['checked_timestamp']); ?></td>
                        <td>
                            <span class="dw-speedtest-type <?= $result['test_type'] === 'automatic' ? 'automatic' : 'manual'; ?>">
                                <?= $result['test_type'] === 'automatic' ? 'Automatic' : 'Manual'; ?>
                            </span>
                        </td>
                        <?php if ($result['error_message'] !== null): ?>
                            <td colspan="3">Failed</td>
                            <td><?= htmlspecialchars((string) $result['error_message'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php else: ?>
                            <td><?= htmlspecialchars((string) $result['download_mbps'], ENT_QUOTES, 'UTF-8'); ?> Mbps</td>
                            <td><?= htmlspecialchars((string) $result['upload_mbps'], ENT_QUOTES, 'UTF-8'); ?> Mbps</td>
                            <td><?= htmlspecialchars((string) $result['ping_ms'], ENT_QUOTES, 'UTF-8'); ?> ms</td>
                            <td><?= htmlspecialchars(trim((string) $result['server_name'] . ' ' . (string) $result['server_location']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<script>
document.getElementById('dw-speedtest-run-form').addEventListener('submit', function () {
    const button = document.getElementById('dw-speedtest-run-button');
    button.disabled = true;
    button.textContent = 'Test in progress…';
    document.getElementById('dw-speedtest-progress').hidden = false;
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
