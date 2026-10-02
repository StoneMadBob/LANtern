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
