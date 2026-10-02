<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_admin();
$csrfToken = csrf_token();

ob_start();

// Handle messages
$message = '';
$message_type = '';

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = ($_GET['type'] === 'error') ? 'error' : 'success';
}

/* -----------------------------------------------------------
   FETCH ALL DEVICES
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT * FROM devices ORDER BY `group`, category, name");
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------------------------------------
   BUILD CATEGORY → DEVICES STRUCTURE
----------------------------------------------------------- */
$device_categories = [];

foreach ($devices as $d) {
    $category = $d['category'];

    if (!isset($device_categories[$category])) {
        $device_categories[$category] = [];
    }

    $device_categories[$category][] = $d;
}
?>

<h1>Manage Devices</h1>

<?php if (!empty($message)): ?>
    <div class="alert <?= $message_type; ?>">
        <?= $message; ?>
    </div>
<?php endif; ?>

<p>
    <a href="add_device.php" class="dw-btn dw-btn-primary">➕ Add New Device</a>
</p>

<?php foreach ($device_categories as $category => $items): ?>
    <h2><?= htmlspecialchars($category); ?></h2>

    <div class="dw-device-grid">
        <?php foreach ($items as $d): ?>
            <div class="dw-device-card">

                <div class="dw-device-header">
                    <strong><?= htmlspecialchars($d['name']); ?></strong>
                    <span class="dw-status-led <?= htmlspecialchars($d['status']); ?>"></span>
                </div>

                <div class="dw-device-meta">
                    <?= htmlspecialchars($d['type']); ?> •
                    <?= htmlspecialchars($d['hostname']); ?> •
                    <?= htmlspecialchars($d['ip']); ?>
                </div>

                <?php if (!empty($d['notes'])): ?>
                    <div class="dw-device-notes">
                        <?= sanitize_allowed_html($d['notes']); ?>
                    </div>
                <?php endif; ?>

                <div class="dw-device-actions">
                    <a href="/admin/device.php?id=<?= $d['id']; ?>" class="dw-btn dw-btn-edit">👁️ View</a>
                    <a href="/admin/edit_device.php?id=<?= $d['id']; ?>" class="dw-btn dw-btn-primary">✏️ Edit</a>
                    <form action="/admin/delete_device.php" method="post" onsubmit="return confirm('Delete this device?');">
                        <input type="hidden" name="id" value="<?= (int) $d['id']; ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="dw-btn dw-btn-delete">🗑️ Delete</button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
