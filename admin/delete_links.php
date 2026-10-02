<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_roles(['admin', 'editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

// Get link ID
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: manage_links.php?msg=Invalid link ID&type=error");
    exit;
}

// Delete the link
$stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");

if ($stmt->execute([$id])) {
    header("Location: manage_links.php?msg=Link deleted successfully&type=success");
    exit;
} else {
    header("Location: manage_links.php?msg=Failed to delete link&type=error");
    exit;
}
