<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_roles(['admin', 'editor']);

if (empty($_SESSION['announcement_csrf'])) {
    $_SESSION['announcement_csrf'] = bin2hex(random_bytes(32));
}

function announcement_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: manage_announcements.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, title, body, created_at FROM announcements WHERE id = ?');
$stmt->execute([$id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    header('Location: manage_announcements.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['announcement_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = sanitize_allowed_html(trim($_POST['body'] ?? ''));

        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        } else {
            $update = $pdo->prepare(
                'UPDATE announcements SET title = ?, body = ? WHERE id = ?'
            );
            $update->execute([$title, $body, $id]);
            header('Location: manage_announcements.php?msg=Announcement updated successfully&type=success');
            exit;
        }

        $announcement['title'] = $title;
        $announcement['body'] = $body;
    }
}

ob_start();
?>
<h2>Edit Announcement</h2>

<?php if ($error): ?>
    <div class="alert error"><?= announcement_escape($error); ?></div>
<?php endif; ?>

<form method="post" class="dw-form">
    <input type="hidden" name="csrf" value="<?= announcement_escape($_SESSION['announcement_csrf']); ?>">

    <label for="title">Title</label>
    <input id="title" type="text" name="title" maxlength="200" value="<?= announcement_escape($announcement['title']); ?>" required>

    <label for="body">Body</label>
    <textarea id="body" name="body" rows="8" required><?= announcement_escape($announcement['body']); ?></textarea>

    <button type="submit" class="dw-btn dw-btn-primary">Save Changes</button>
    <a href="manage_announcements.php" class="dw-btn dw-btn-edit">Cancel</a>
</form>
<?php
$content = ob_get_clean();
include '../templates/layout.php';