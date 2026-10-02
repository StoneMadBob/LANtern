<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/uploads.php';

require_roles(['admin', 'editor']);

$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    header('Location: manage_uploads.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = ?');
$stmt->execute([$id]);
$upload = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$upload) {
    header('Location: manage_uploads.php');
    exit;
}

$csrfToken = csrf_token();
$error = '';
$name = pathinfo($upload['filename'], PATHINFO_FILENAME);
$description = $upload['description'] ?? '';
$extension = strtolower(pathinfo($upload['path'], PATHINFO_EXTENSION));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $name = is_string($name) ? trim($name) : '';
    $description = is_string($description) ? upload_plain_text_description($description) : '';

    if ($name === '') {
        $error = 'A file name is required.';
    } elseif (strlen($description) > 4000) {
        $error = 'Descriptions must be 4,000 characters or fewer.';
    } else {
        $filename = upload_display_name($name, $extension);
        $stmt = $pdo->prepare('UPDATE uploads SET filename = ?, description = ? WHERE id = ?');
        $stmt->execute([$filename, $description, $id]);
        header('Location: manage_uploads.php');
        exit;
    }
}

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$safeExtension = htmlspecialchars($extension, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<h2>Edit Upload</h2>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="post" class="dw-form">
    <input type="hidden" name="id" value="<?= (int) $id; ?>">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <label for="upload-name">File name</label>
    <input id="upload-name" type="text" name="name" value="<?= $safeName; ?>" maxlength="180" required>
    <small>The .<?= $safeExtension; ?> extension is kept automatically.</small>
    <label for="upload-description">Description</label>
    <textarea id="upload-description" name="description" maxlength="4000" rows="4"><?= $safeDescription; ?></textarea>
    <button type="submit" class="dw-btn dw-btn-primary">Save Changes</button>
    <a href="manage_uploads.php" class="dw-btn dw-btn-edit">Cancel</a>
</form>
<?php
$content = ob_get_clean();
include '../templates/layout.php';