<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_roles(['admin', 'editor']);

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
$stmt = $pdo->query("SELECT DISTINCT `group` FROM links ORDER BY `group`");
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* -----------------------------------------------------------
   FETCH DISTINCT CATEGORIES
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT DISTINCT `category` FROM links ORDER BY `category`");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* -----------------------------------------------------------
   HANDLE FORM SUBMISSION
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name         = trim($_POST['name'] ?? '');
    $url          = trim($_POST['url'] ?? '');
    $host         = trim($_POST['host'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $new_category = trim($_POST['new_category'] ?? '');
    $group        = trim($_POST['group'] ?? '');
    $new_group    = trim($_POST['new_group'] ?? '');

    // Allow creation of a new category
    if (!empty($new_category)) {
        $category = $new_category;
    }

    // Allow creation of a new group
    if (!empty($new_group)) {
        $group = $new_group;
    }

    if (empty($name) || empty($url) || empty($category) || empty($group)) {
        header("Location: add_links.php?msg=All fields are required&type=error");
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO links (name, url, host, category, `group`, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if ($stmt->execute([$name, $url, $host, $category, $group, $description])) {
        header("Location: manage_links.php?msg=Link added successfully&type=success");
        exit;
    } else {
        header("Location: add_links.php?msg=Failed to add link&type=error");
        exit;
    }
}
?>

<h1>Add Link</h1>

<?php if (!empty($message)): ?>
    <div class="alert <?= $message_type; ?>">
        <?= $message; ?>
    </div>
<?php endif; ?>

<form method="post">

    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>
    <label>URL:</label><br>
    <input type="text" name="url" required><br><br>
    <label>Host (optional):</label><br>
    <input type="text" name="host"><br><br>
    <label>Description (optional):</label>
    <textarea name="description" class="dw-textarea"></textarea>
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

    <button type="submit" class="dw-btn dw-btn-primary">➕ Add Link</button>

</form>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
