<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/plugins.php';

$dashboardPlugins = array_values(array_filter(
    get_dashboard_plugin_widgets($pdo),
    static fn($plugin) => ($plugin['status'] ?? '') !== 'not-configured'
));

ob_start();
?>
<h2>Service Stats</h2>
<p><a href="/dashboard.php">Back to Dashboard</a></p>

<?php if (empty($dashboardPlugins)): ?>
    <p>No service integrations are configured.</p>
<?php endif; ?>

<?php foreach ($dashboardPlugins as $plugin): ?>
    <?php
    $pluginStatus = (string) ($plugin['status'] ?? 'unavailable');
    $pluginLedStatus = in_array($pluginStatus, ['online', 'offline', 'maintenance'], true) ? $pluginStatus : 'offline';
    ?>
    <section class="dw-panel dw-plugin-panel">
        <h3>
            <?= htmlspecialchars((string) ($plugin['name'] ?? 'Plugin'), ENT_QUOTES, 'UTF-8'); ?>
            <span class="dw-status-led <?= htmlspecialchars($pluginLedStatus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
            <small><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $pluginStatus)), ENT_QUOTES, 'UTF-8'); ?></small>
        </h3>
        <p><?= htmlspecialchars((string) ($plugin['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($plugin['items']) && is_array($plugin['items'])): ?>
            <ul class="dw-issue-list">
                <?php foreach ($plugin['items'] as $item): ?>
                    <?php if (!is_array($item)) { continue; } ?>
                    <?php $itemStatus = in_array(($item['status'] ?? ''), ['online', 'offline', 'maintenance'], true) ? $item['status'] : 'offline'; ?>
                    <li class="dw-issue-item">
                        <span class="dw-status-led <?= htmlspecialchars($itemStatus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <div>
                            <strong><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if (!empty($item['detail'])): ?>
                                <br><small><?= htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($plugin['url'])): ?>
            <p><a href="<?= htmlspecialchars((string) $plugin['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open <?= htmlspecialchars((string) ($plugin['name'] ?? 'service'), ENT_QUOTES, 'UTF-8'); ?></a></p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';