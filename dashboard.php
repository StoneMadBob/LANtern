<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/ping.php';
require_once 'includes/sanitize.php';
require_once 'includes/plugins.php';

ob_start(); // Start output buffering so layout.php can wrap the page


/* -----------------------------------------------------------
   FETCH ANNOUNCEMENTS (latest 3)
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -----------------------------------------------------------
   FETCH LINKS AND GROUP BY CATEGORY
----------------------------------------------------------- */

// Fetch links
$stmt = $pdo->query("SELECT * FROM links ORDER BY category, sort_order, name");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build dynamic groups from DB (group → category → items)
$groups = [];

foreach ($links as $link) {
    $groupName = $link['group'];
    $categoryName = $link['category'];

    if (!isset($groups[$groupName])) {
        $groups[$groupName] = [];
    }

    if (!isset($groups[$groupName][$categoryName])) {
        $groups[$groupName][$categoryName] = [];
    }

    $groups[$groupName][$categoryName][] = $link;
}

/* -----------------------------------------------------------
   FETCH DEVICES FOR DASHBOARD
----------------------------------------------------------- */

$stmt = $pdo->query("SELECT * FROM devices ORDER BY `group`, category, name");
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$device_groups = [];

foreach ($devices as $d) {
    $groupName = $d['group'];
    $categoryName = $d['category'];

    if (!isset($device_groups[$groupName])) {
        $device_groups[$groupName] = [];
    }

    if (!isset($device_groups[$groupName][$categoryName])) {
        $device_groups[$groupName][$categoryName] = [];
    }

    $device_groups[$groupName][$categoryName][] = $d;
}

$announcementCount = (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
$linkCount = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
$deviceCount = (int)$pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

$onlineDeviceCount = 0;
$offlineDeviceCount = 0;
$maintenanceDeviceCount = 0;
$lastPingCheck = date('H:i:s');
$unmonitorableDeviceCount = 0;
$staleMaintenanceCount = 0;
$statusWarnings = [];
$deviceHealth = [];

foreach ($devices as $device) {
    $status = strtolower((string)($device['status'] ?? ''));
    $deviceId = $device['id'];
    $ignoreActiveIssues = !empty($device['ignore_active_issues']);

    if ($status === 'maintenance') {
        $maintenanceDeviceCount++;
        $deviceHealth[$deviceId] = 'maintenance';

        $updatedAt = isset($device['updated_at']) && $device['updated_at'] ? new DateTimeImmutable($device['updated_at']) : null;
        if ($updatedAt !== null && $updatedAt->modify('+7 days') < new DateTimeImmutable()) {
            $staleMaintenanceCount++;
        }

        if ($ignoreActiveIssues) {
            continue;
        }
        continue;
    }

    $target = !empty($device['ip']) ? (string) $device['ip'] : ((string) ($device['hostname'] ?? ''));
    if ($target === '') {
        $unmonitorableDeviceCount++;
    }

    $isResponsive = $target !== '' && ping_host($target, 0.5);
    $liveStatus = $isResponsive ? 'online' : 'offline';
    $deviceHealth[$deviceId] = $liveStatus;

    if ($liveStatus === 'online') {
        $onlineDeviceCount++;
        continue;
    }

    $offlineDeviceCount++;

    if ($ignoreActiveIssues) {
        continue;
    }

    if (!in_array($status, ['offline', 'maintenance'], true)) {
        $warningName = trim((string) ($device['name'] ?: $target));
        if ($warningName !== '') {
            $statusWarnings[] = $warningName;
        }
    }
}

$statusWarnings = array_values(array_unique(array_filter($statusWarnings, static fn($value) => trim((string) $value) !== '')));
$activeIssues = [];
foreach ($devices as $device) {
    if (!empty($device['ignore_active_issues'])) {
        continue;
    }

    $status = strtolower((string)($device['status'] ?? ''));
    $deviceId = $device['id'];
    $live = $deviceHealth[$deviceId] ?? 'offline';

    if ($status === 'maintenance') {
        $activeIssues[] = [
            'name' => $device['name'],
            'group' => $device['group'],
            'status' => 'maintenance',
            'id' => $device['id']
        ];
        continue;
    }

    if ($live === 'offline') {
        $activeIssues[] = [
            'name' => $device['name'],
            'group' => $device['group'],
            'status' => 'offline',
            'id' => $device['id']
        ];
    }
}

usort($activeIssues, static function ($a, $b) {
    $priority = ['maintenance' => 0, 'offline' => 1];
    $aPriority = $priority[$a['status']] ?? 99;
    $bPriority = $priority[$b['status']] ?? 99;

    if ($aPriority !== $bPriority) {
        return $aPriority <=> $bPriority;
    }

    return strcmp((string) $a['name'], (string) $b['name']);
});

$role = $_SESSION['role'] ?? 'user';
$isAdmin = $role === 'admin';
$canEditContent = in_array($role, ['admin', 'editor'], true);
$canManageKb = in_array($role, ['admin', 'editor', 'author'], true);
$dashboardPlugins = array_values(array_filter(
    get_dashboard_plugin_widgets($pdo),
    static fn($plugin) => ($plugin['status'] ?? '') !== 'not-configured'
));

?>

<!-- ---------------------------------------------------------
     PAGE TITLE
---------------------------------------------------------- -->
<h2>Dashboard</h2>

<?php if (!empty($statusWarnings)): ?>
    <div class="alert error">
        Warning: <?= htmlspecialchars(implode(', ', $statusWarnings), ENT_QUOTES, 'UTF-8'); ?> is not responding to ping and is not marked offline or maintenance.
    </div>
<?php endif; ?>

<section class="dw-panel">
    <button type="button" class="dw-panel-toggle open">Overview</button>
    <div class="dw-panel-body open">
        <div class="dw-stats-grid">
            <div class="dw-stat-card">
                <span class="dw-stat-label">Announcements</span>
                <strong class="dw-stat-value"><?= $announcementCount; ?></strong>
            </div>
            <div class="dw-stat-card">
                <span class="dw-stat-label">Links</span>
                <strong class="dw-stat-value"><?= $linkCount; ?></strong>
            </div>
            <div class="dw-stat-card">
                <span class="dw-stat-label">Devices</span>
                <strong class="dw-stat-value"><?= $deviceCount; ?></strong>
            </div>
            <div class="dw-stat-card">
                <span class="dw-stat-label">Users</span>
                <strong class="dw-stat-value"><?= $userCount; ?></strong>
            </div>
            <div class="dw-stat-card accent-cyan">
                <span class="dw-stat-label">Last check</span>
                <strong class="dw-stat-value dw-stat-small"><?= htmlspecialchars($lastPingCheck); ?></strong>
            </div>
            <div class="dw-stat-card accent-gold">
                <span class="dw-stat-label">Unmonitorable</span>
                <strong class="dw-stat-value"><?= $unmonitorableDeviceCount; ?></strong>
            </div>
            <div class="dw-stat-card accent-orange">
                <span class="dw-stat-label">Maintenance 7+ days</span>
                <strong class="dw-stat-value"><?= $staleMaintenanceCount; ?></strong>
            </div>
            <div class="dw-status-stack">
                <div class="dw-stat-card accent-blue">
                    <span class="dw-stat-label">Online</span>
                    <strong class="dw-stat-value"><?= $onlineDeviceCount; ?></strong>
                </div>
                <div class="dw-stat-card accent-red">
                    <span class="dw-stat-label">Offline</span>
                    <strong class="dw-stat-value"><?= $offlineDeviceCount; ?></strong>
                </div>
                <div class="dw-stat-card accent-amber">
                    <span class="dw-stat-label">Maintenance</span>
                    <strong class="dw-stat-value"><?= $maintenanceDeviceCount; ?></strong>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($canEditContent || $canManageKb || $isAdmin): ?>
    <section class="dw-panel">
        <button type="button" class="dw-panel-toggle open">Quick Actions</button>
        <div class="dw-panel-body open">
            <div class="dw-quick-actions">
                <?php if ($isAdmin): ?>
                    <a href="/admin/manage_devices.php" class="dw-action-card">
                        <span class="dw-action-title">Manage Devices</span>
                        <span class="dw-action-meta">View and update hardware</span>
                    </a>
                <?php endif; ?>
                <?php if ($canEditContent): ?>
                    <a href="/admin/manage_announcements.php" class="dw-action-card">
                        <span class="dw-action-title">Announcements</span>
                        <span class="dw-action-meta">Post site updates</span>
                    </a>
                <?php endif; ?>
                <?php if ($canManageKb): ?>
                    <a href="/admin/manage_kb.php" class="dw-action-card">
                        <span class="dw-action-title">Knowledge Base</span>
                        <span class="dw-action-meta">Add or update guides</span>
                    </a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <a href="/admin/settings.php" class="dw-action-card">
                        <span class="dw-action-title">Settings</span>
                        <span class="dw-action-meta">Site configuration</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($activeIssues)): ?>
    <section class="dw-panel">
        <button type="button" class="dw-panel-toggle open">Active Issues (<?= count($activeIssues); ?>)</button>
        <div class="dw-panel-body open">
            <ul class="dw-issue-list">
                <?php foreach ($activeIssues as $issue): ?>
                    <li class="dw-issue-item dw-issue-item--<?= htmlspecialchars($issue['status']); ?>">
                        <span class="dw-status-led <?= htmlspecialchars($issue['status']); ?>" aria-hidden="true"></span>
                        <div>
                            <strong><?= htmlspecialchars($issue['name']); ?></strong><br>
                            <small><?= htmlspecialchars($issue['group']); ?> • <?= ucfirst(htmlspecialchars($issue['status'])); ?></small>
                        </div>
                        <a href="/admin/device.php?id=<?= (int)$issue['id']; ?>" class="dw-btn dw-btn-edit">View</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($dashboardPlugins)): ?>
    <section class="dw-panel dw-plugin-summary">
        <h3 class="dw-panel-title">Service Status</h3>
        <?php foreach ($dashboardPlugins as $plugin): ?>
            <?php
            $pluginStatus = (string) ($plugin['status'] ?? 'unavailable');
            $pluginLedStatus = in_array($pluginStatus, ['online', 'offline', 'maintenance'], true) ? $pluginStatus : 'offline';
            ?>
            <div class="dw-plugin-summary-row">
                <div class="dw-plugin-summary-details">
                    <strong>
                        <?= htmlspecialchars((string) ($plugin['name'] ?? 'Plugin'), ENT_QUOTES, 'UTF-8'); ?>
                        <span class="dw-status-led <?= htmlspecialchars($pluginLedStatus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <small><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $pluginStatus)), ENT_QUOTES, 'UTF-8'); ?></small>
                    </strong>
                    <p><?= htmlspecialchars((string) ($plugin['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="dw-plugin-summary-actions">
                    <a href="/plugin_stats.php" class="dw-btn dw-btn-edit">Full stats</a>
                    <?php if (!empty($plugin['url'])): ?>
                        <a href="<?= htmlspecialchars((string) $plugin['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open <?= htmlspecialchars((string) ($plugin['name'] ?? 'service'), ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- ---------------------------------------------------------
     LATEST ANNOUNCEMENTS SECTION
---------------------------------------------------------- -->
<section class="dw-announcements">
    <h3>Latest Announcements</h3>

    <?php if (empty($announcements)): ?>
        <p>No announcements yet.</p>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="dw-announcement">
                <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                <small><?= $a['created_at'] ?></small>
                <p><?= sanitize_allowed_html($a['body']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- ---------------------------------------------------------
     Devices Section
---------------------------------------------------------- -->

<h2>Live Status</h2>

<div class="dw-status-filter">
    <button type="button" class="dw-filter-btn active" data-filter="all">All</button>
    <button type="button" class="dw-filter-btn" data-filter="online">Online</button>
    <button type="button" class="dw-filter-btn" data-filter="offline">Offline</button>
    <button type="button" class="dw-filter-btn" data-filter="maintenance">Maintenance</button>
</div>

<?php foreach ($device_groups as $groupName => $categories): ?>
    <div class="dw-panel">
        <button type="button" class="dw-panel-toggle"><?= htmlspecialchars($groupName); ?></button>
        <div class="dw-panel-body">

            <?php foreach ($categories as $categoryName => $items): ?>
                <h3><?= htmlspecialchars($categoryName); ?></h3>

                <div class="dw-device-grid">

                    <?php foreach ($items as $item): ?>
                        <?php
                        $live = $deviceHealth[$item['id']] ?? 'offline';
                        $deviceStatus = ($item['status'] ?? '') === 'maintenance' ? 'maintenance' : $live;
                        ?>

                        <div class="dw-device-card" data-status="<?= htmlspecialchars($deviceStatus); ?>">

                            <div class="dw-device-header">
                                <strong><?= htmlspecialchars($item['name']); ?></strong>
                                <span class="dw-status-led <?= $live; ?>"></span>
                            </div>

                            <div class="dw-device-meta">
                                <?= htmlspecialchars($item['type']); ?><br>
                                <?= htmlspecialchars($item['hostname']); ?><br>
                                <?= htmlspecialchars($item['ip']); ?>
                            </div>

                            <div class="dw-device-actions">
                                <a href="/admin/device.php?id=<?= $item['id']; ?>" class="dw-btn dw-btn-edit">
                                    View
                                </a>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endforeach; ?>

        </div>
    </div>
<?php endforeach; ?>

<?php
// Capture all buffered output into $content
$content = ob_get_clean();

// Render inside your layout
include 'templates/layout.php';
