<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_roles(['admin', 'editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

// Validate ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Location: manage_kb.php');
    exit;
}

$article_id = (int)$_POST['id'];

// Delete article (tag relationships cascade automatically)
$stmt = $pdo->prepare("DELETE FROM kb_articles WHERE id = ?");
$stmt->execute([$article_id]);

header('Location: manage_kb.php');
exit;
