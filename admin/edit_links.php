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
   GET LINK ID
----------------------------------------------------------- */
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: manage_links.php?msg=Invalid link ID&type=error");
    exit;
}

/* -----------------------------------------------------------
   FETCH EXISTING LINK
----------------------------------------------------------- */
$stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
$stmt->execute([$id]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) {
    header("Location: manage_links.php?msg=Link not found&type=error");
    exit;
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
    $category     = trim($_POST['category'] ?? '');
    $new_category = trim($_POST['new_category'] ?? '');
    $group        = trim($_POST['group'] ?? '');
    $new_group    = trim($_POST['new_group'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (!empty($new_category)) {
        $category = $new_category;
    }

    if (!empty($new_group)) {
        $group = $new_group;
    }

    if (empty($name) || empty($url) || empty($category) || empty($group)) {
        header("Location: edit_links.php?id=$id&msg=All fields are required&type=error");
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE links
        SET name = ?, url = ?, host = ?, category = ?, `group` = ?, description = ?
        WHERE id = ?
    ");

    if ($stmt->execute([$name, $url, $host, $category, $group, $description, $id])) {
        header("Location: manage_links.php?msg=Link updated successfully&type=success");
        exit;
    } else {
        header("Location: edit_links.php?id=$id&msg=Failed to update link&type=error");
        exit;
    }
}

?>

<h1>Edit Link</h1>

<?php if (!empty($message)): ?>
    <div class="alert <?= $message_type; ?>">
        <?= $message; ?>
    </div>
<?php endif; ?>

<form method="post">

    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($link['name']); ?>" required><br><br>
    <label>URL:</label><br>
    <input type="text" name="url" value="<?= htmlspecialchars($link['url']); ?>" required><br><br>
    <label>Host (optional):</label><br>
    <input type="text" name="host" value="<?= htmlspecialchars($link['host']); ?>"><br><br>
    <label>Description</label>
    <textarea name="description" class="dw-textarea"><?= htmlspecialchars($link['description'] ?? '') ?></textarea>
    <label>Category:</label><br>
    <select name="category">
        <?php foreach ($categories as $c): ?>
            <option value="<?= htmlspecialchars($c); ?>"
                <?= ($c === $link['category']) ? 'selected' : ''; ?>>
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
            <option value="<?= htmlspecialchars($g); ?>"
                <?= ($g === $link['group']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($g); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label>Or create new group:</label><br>
    <input type="text" name="new_group" placeholder="Enter new group name">
    <br><br>

    <button type="submit" class="dw-btn dw-btn-primary">✏️ Save Changes</button>

</form>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
