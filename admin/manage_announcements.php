<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_roles(['admin', 'editor']);
$csrfToken = csrf_token();

// Handle add
if (isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO announcements (title, body) VALUES (?, ?)");
    $stmt->execute([
        $_POST['title'],
        sanitize_allowed_html($_POST['body'])
    ]);
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $id = filter_var($_POST['delete'], FILTER_VALIDATE_INT);
    if ($id !== false) {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
    }

    header('Location: manage_announcements.php');
    exit;
}

// Fetch announcements
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = '
<h2>Manage Announcements</h2>

<h3>Add New Announcement</h3>
<form method="POST">
    <label>Title</label><br>
    <input type="text" name="title" required><br><br>

    <label>Body</label><br>
    <textarea name="body" rows="5" required></textarea><br><br>

    <button type="submit" name="add" class="dw-btn dw-btn-primary">➕ Post Announcement</button>
</form>

<hr>

<h3>Existing Announcements</h3>
<table class="dw-table dw-announcements-table">
<tr>
    <th>Title</th>
    <th>Body</th>
    <th>Posted</th>
    <th>Edit</th>
    <th>Delete</th>
</tr>
';

foreach ($announcements as $a) {
    $content .= "
    <tr>
        <td>".htmlspecialchars($a['title'])."</td>
        <td>".sanitize_allowed_html($a['body'])."</td>
        <td>{$a['created_at']}</td>
        <td>
        <a href='edit_announcement.php?id={$a['id']}' class='dw-btn dw-btn-edit'>
            ✏️ Edit
        </a>
        </td>
        <td>
        <form method='post' action='manage_announcements.php' onsubmit='return confirm(&quot;Delete this announcement?&quot;);'>
            <input type='hidden' name='csrf' value='{$csrfToken}'>
            <button type='submit' name='delete' value='{$a['id']}' class='dw-btn dw-btn-delete'>🗑️ Delete</button>
        </form>
 </td>

    </tr>
    ";
}

$content .= "</table>";

include '../templates/layout.php';
