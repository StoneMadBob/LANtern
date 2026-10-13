<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_roles(['admin', 'editor', 'author']);
$csrfToken = csrf_token();

ob_start();

/* -----------------------------------------------------------
   GET DEVICE ID
----------------------------------------------------------- */
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: manage_devices.php?msg=Invalid device ID&type=error");
    exit;
}

/* -----------------------------------------------------------
   FETCH DEVICE
----------------------------------------------------------- */
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: manage_devices.php?msg=Device not found&type=error");
    exit;
}

/* -----------------------------------------------------------
   PING CHECK OF DEVICE
----------------------------------------------------------- */

require_once '../includes/ping.php';

$live_status = null;

if (!empty($device['ip'])) {
    $live_status = ping_host($device['ip']) ? 'online' : 'offline';
} elseif (!empty($device['hostname'])) {
    $live_status = ping_host($device['hostname']) ? 'online' : 'offline';
}

$ping_target = trim((string) ($device['ip'] ?? ''));
if ($ping_target === '') {
    $ping_target = trim((string) ($device['hostname'] ?? ''));
}

$ping_history = [];
if ($ping_target !== '') {
    $stmt = $pdo->prepare("
        SELECT UNIX_TIMESTAMP(checked_at) AS checked_timestamp, success, latency_ms
        FROM ping_results
        WHERE device_id = ? AND checked_at >= NOW() - INTERVAL 24 HOUR
        ORDER BY checked_at
    ");
    $stmt->execute([(int) $device['id']]);
    $ping_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------------------------------------
   FETCH RELATED LINKS
----------------------------------------------------------- */
$related_links = [];

if (!empty($device['hostname']) || !empty($device['ip'])) {

    $stmt = $pdo->prepare("
        SELECT * FROM links
        WHERE url LIKE CONCAT('%', ?, '%')
           OR url LIKE CONCAT('%', ?, '%')
        ORDER BY name
    ");

    $stmt->execute([
        $device['hostname'] ?? '',
        $device['ip'] ?? ''
    ]);

    $related_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<h1>Device Details</h1>

<?php if ($_SESSION['role'] === 'admin'): ?>
    <p>
        <a href="edit_device.php?id=<?= $device['id'] ?>" class="dw-btn dw-btn-edit">✏️ Edit Device</a>
        <form action="delete_device.php" method="post" onsubmit="return confirm('Delete this device?');">
            <input type="hidden" name="id" value="<?= (int) $device['id']; ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="dw-btn dw-btn-delete">🗑️ Delete Device</button>
        </form>
    </p>
<?php endif; ?>

<table class="dw-table">
    <tbody>
        <tr>
            <th>Name</th>
            <td><?= htmlspecialchars($device['name']); ?></td>
        </tr>

        <tr>
            <th>Type</th>
            <td><?= htmlspecialchars($device['type']); ?></td>
        </tr>

        <tr>
            <th>Hostname</th>
            <td><?= htmlspecialchars($device['hostname']); ?></td>
        </tr>

        <tr>
            <th>IP Address</th>
            <td><?= htmlspecialchars($device['ip']); ?></td>
        </tr>

        <tr>
            <th>Operating System / Firmware</th>
            <td><?= htmlspecialchars($device['os']); ?></td>
        </tr>

        <tr>
            <th>Location</th>
            <td><?= htmlspecialchars($device['location']); ?></td>
        </tr>

        <tr>
            <th>Live Status</th>
            <td>
                <span class="dw-status <?= $live_status; ?>">
                    <?= ucfirst($live_status); ?>
                </span>
            </td>
        </tr>

        <tr>
            <th>Group</th>
            <td><?= htmlspecialchars($device['group']); ?></td>
        </tr>

        <tr>
            <th>Category</th>
            <td><?= htmlspecialchars($device['category']); ?></td>
        </tr>

        <tr>
            <th>Notes</th>
            <td><?= sanitize_allowed_html($device['notes']); ?></td>
        </tr>

        <tr>
            <th>Created</th>
            <td><?= htmlspecialchars($device['created_at']); ?></td>
        </tr>

        <tr>
            <th>Last Updated</th>
            <td><?= htmlspecialchars($device['updated_at']); ?></td>
        </tr>
    </tbody>
</table>

<?php if ($ping_target !== ''): ?>
    <?php
    $chart_width = 960;
    $chart_height = 280;
    $chart_left = 52;
    $chart_right = 18;
    $chart_top = 20;
    $chart_bottom = 42;
    $plot_width = $chart_width - $chart_left - $chart_right;
    $plot_height = $chart_height - $chart_top - $chart_bottom;
    $window_start = time() - 86400;
    $latencies = array_map(
        static function ($sample) {
            return $sample['latency_ms'] === null ? null : (float) $sample['latency_ms'];
        },
        $ping_history
    );
    $valid_latencies = array_filter($latencies, static function ($value) {
        return $value !== null;
    });
    $max_latency = $valid_latencies ? max($valid_latencies) : 0;
    $latency_scale = max(10, ceil($max_latency / 10) * 10);
    $successful_pings = count(array_filter($ping_history, static function ($sample) {
        return (int) $sample['success'] === 1;
    }));
    $response_rate = $ping_history
        ? round(($successful_pings / count($ping_history)) * 100, 1)
        : null;
    ?>
    <h2>Ping History (Last 24 Hours)</h2>
    <?php if (!$ping_history): ?>
        <p>No ping history yet. Once the scheduled collector is configured, results will appear here.</p>
    <?php else: ?>
        <p>
            <?= count($ping_history); ?> checks ·
            <?= htmlspecialchars((string) $response_rate, ENT_QUOTES, 'UTF-8'); ?>% response rate
            <?php if ($valid_latencies): ?>
                · <?= htmlspecialchars((string) min($valid_latencies), ENT_QUOTES, 'UTF-8'); ?>–
                <?= htmlspecialchars((string) max($valid_latencies), ENT_QUOTES, 'UTF-8'); ?> ms latency
            <?php endif; ?>
        </p>
        <svg class="dw-ping-chart" viewBox="0 0 <?= $chart_width; ?> <?= $chart_height; ?>"
             role="img" aria-label="Ping response latency over the last 24 hours. Red marks indicate failed pings.">
            <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                <?php
                $y = $chart_top + ($plot_height * $tick / 4);
                $tick_value = $latency_scale * (4 - $tick) / 4;
                ?>
                <line x1="<?= $chart_left; ?>" y1="<?= $y; ?>" x2="<?= $chart_width - $chart_right; ?>"
                      y2="<?= $y; ?>" class="dw-ping-gridline" />
                <text x="<?= $chart_left - 8; ?>" y="<?= $y + 4; ?>" text-anchor="end"
                      class="dw-ping-axis"><?= htmlspecialchars((string) round($tick_value), ENT_QUOTES, 'UTF-8'); ?></text>
            <?php endfor; ?>
            <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                <?php
                $x = $chart_left + ($plot_width * $tick / 4);
                $label_time = $window_start + (86400 * $tick / 4);
                ?>
                <text x="<?= $x; ?>" y="<?= $chart_height - 12; ?>" text-anchor="middle"
                      class="dw-ping-axis"><?= date('H:i', (int) $label_time); ?></text>
            <?php endfor; ?>
            <text x="15" y="<?= $chart_top + ($plot_height / 2); ?>" text-anchor="middle"
                  class="dw-ping-axis" transform="rotate(-90 15 <?= $chart_top + ($plot_height / 2); ?>)">Latency (ms)</text>
            <?php
            $segments = [];
            $segment = [];
            $failure_points = [];
            foreach ($ping_history as $sample) {
                if ((int) $sample['success'] !== 1 || $sample['latency_ms'] === null) {
                    if ($segment) {
                        $segments[] = $segment;
                        $segment = [];
                    }
                    if ((int) $sample['success'] !== 1) {
                        $failure_points[] = $sample;
                    }
                    continue;
                }

                $x = $chart_left + (
                    ((int) $sample['checked_timestamp'] - $window_start) / 86400 * $plot_width
                );
                $y = $chart_top + (
                    1 - min((float) $sample['latency_ms'], $latency_scale) / $latency_scale
                ) * $plot_height;
                $segment[] = [
                    'x' => max($chart_left, min($chart_width - $chart_right, $x)),
                    'y' => $y,
                    'sample' => $sample
                ];
            }
            if ($segment) {
                $segments[] = $segment;
            }
            ?>
            <?php foreach ($segments as $points): ?>
                <?php if (count($points) > 1): ?>
                    <polyline class="dw-ping-line" points="<?php
                        echo htmlspecialchars(implode(' ', array_map(static function ($point) {
                            return $point['x'] . ',' . $point['y'];
                        }, $points)), ENT_QUOTES, 'UTF-8');
                    ?>" />
                <?php endif; ?>
                <?php foreach ($points as $point): ?>
                    <circle class="dw-ping-point" cx="<?= $point['x']; ?>" cy="<?= $point['y']; ?>" r="3">
                        <title><?= date('Y-m-d H:i:s', (int) $point['sample']['checked_timestamp']); ?> ·
                            <?= htmlspecialchars((string) $point['sample']['latency_ms'], ENT_QUOTES, 'UTF-8'); ?> ms</title>
                    </circle>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php foreach ($failure_points as $sample): ?>
                <?php
                $x = $chart_left + (
                    ((int) $sample['checked_timestamp'] - $window_start) / 86400 * $plot_width
                );
                $x = max($chart_left, min($chart_width - $chart_right, $x));
                $y = $chart_top + $plot_height - 5;
                ?>
                <path class="dw-ping-failure" d="M <?= $x - 4; ?> <?= $y - 4; ?> L <?= $x + 4; ?> <?= $y + 4; ?> M <?= $x + 4; ?> <?= $y - 4; ?> L <?= $x - 4; ?> <?= $y + 4; ?>">
                    <title><?= date('Y-m-d H:i:s', (int) $sample['checked_timestamp']); ?> · No response</title>
                </path>
            <?php endforeach; ?>
        </svg>
        <p class="dw-ping-legend"><span class="dw-ping-legend-line"></span> Response latency
            <span class="dw-ping-legend-failure"></span> No response</p>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($related_links)): ?>
    <h2>Related Links</h2>

    <ul class="dw-link-list">
        <?php foreach ($related_links as $link): ?>
            <li class="dw-link">
                <a href="<?= htmlspecialchars($link['url']); ?>" target="_blank">
                    <?= htmlspecialchars($link['name']); ?>
                </a>
                <span class="dw-small">
                    <?= htmlspecialchars($link['category']); ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <h2>Related Links</h2>
    <p>No related links found.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
