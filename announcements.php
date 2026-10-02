<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/sanitize.php';

ob_start();

// Fetch announcements
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Announcements</h2>

<?php if (empty($announcements)): ?>

    <p>No announcements yet.</p>

<?php else: ?>

    <?php foreach ($announcements as $a): ?>
        <div class="dw-announcement">
            <h3><?php echo htmlspecialchars($a['title']); ?></h3>
            <p><?= sanitize_allowed_html($a['body']); ?></p>
            <small>Posted: <?php echo $a['created_at']; ?></small>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
