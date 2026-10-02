<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_admin();

ob_start();

// Handle success/error messages
$message = '';
$message_type = '';

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = ($_GET['type'] === 'error') ? 'error' : 'success';
}

/* -----------------------------------------------------------
   FETCH DISTINCT GROUPS
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT DISTINCT `group` FROM devices ORDER BY `group`");
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* -----------------------------------------------------------
   FETCH DISTINCT CATEGORIES
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT DISTINCT `category` FROM devices ORDER BY `category`");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* -----------------------------------------------------------
   HANDLE FORM SUBMISSION
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $hostname    = trim($_POST['hostname'] ?? '');
    $ip          = trim($_POST['ip'] ?? '');
    $os          = trim($_POST['os'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $status      = trim($_POST['status'] ?? '');
    $ignoreActiveIssues = isset($_POST['ignore_active_issues']) ? 1 : 0;
    $notes       = sanitize_allowed_html(trim($_POST['notes'] ?? ''));

    $group       = trim($_POST['group'] ?? '');
    $new_group   = trim($_POST['new_group'] ?? '');

    $category    = trim($_POST['category'] ?? '');
    $new_category = trim($_POST['new_category'] ?? '');

    // Allow creation of a new category
    if (!empty($new_category)) {
        $category = $new_category;
    }

    // Allow creation of a new group
    if (!empty($new_group)) {
        $group = $new_group;
    }

    // Required fields
    if (empty($name) || empty($type) || empty($group) || empty($category)) {
        header("Location: add_device.php?msg=Name, type, group and category are required&type=error");
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO devices
        (name, type, hostname, ip, os, location, status, ignore_active_issues, notes, `group`, category)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt->execute([
        $name, $type, $hostname, $ip, $os, $location, $status, $ignoreActiveIssues, $notes, $group, $category
    ])) {
        header("Location: manage_devices.php?msg=Device added successfully&type=success");
        exit;
    } else {
        header("Location: add_device.php?msg=Failed to add device&type=error");
        exit;
    }
}
?>

<h1>Add Device</h1>

<?php if (!empty($message)): ?>
    <div class="alert <?= $message_type; ?>">
        <?= $message; ?>
    </div>
<?php endif; ?>

<form method="post">

    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>Type:</label><br>
    <input type="text" name="type" required><br><br>

    <label>Hostname:</label><br>
    <input type="text" name="hostname"><br><br>

    <label>IP Address:</label><br>
    <input type="text" name="ip"><br><br>

    <label>Operating System / Firmware:</label><br>
    <input type="text" name="os"><br><br>

    <label>Location:</label><br>
    <input type="text" name="location"><br><br>

    <label>Status:</label><br>
    <select name="status">
        <option value="online">Online</option>
        <option value="offline">Offline</option>
        <option value="maintenance">Maintenance</option>
    </select>
    <br><br>

    <label>
        <input type="checkbox" name="ignore_active_issues" value="1">
        Exclude from Active Issues list
    </label>
    <br><br>

    <label>Notes:</label><br>
    <textarea name="notes" rows="4"></textarea>
    <br><br>

    <label>Group:</label><br>
    <select name="group">
        <?php foreach ($groups as $g): ?>
            <option value="<?= htmlspecialchars($g); ?>">
                <?= htmlspecialchars($g); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label>Or create new group:</label><br>
    <input type="text" name="new_group" placeholder="Enter new group name">
    <br><br>

    <label>Category:</label><br>
    <select name="category">
        <?php foreach ($categories as $c): ?>
            <option value="<?= htmlspecialchars($c); ?>">
                <?= htmlspecialchars($c); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label>Or create new category:</label><br>
    <input type="text" name="new_category" placeholder="Enter new category name">
    <br><br>

    <button type="submit" class="dw-btn dw-btn-primary">➕ Add Device</button>

</form>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
