<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';

$siteName = get_app_setting($pdo, 'site_name', 'LANtern');
?>
<footer class="dw-footer">
    <p>© <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></p>
</footer>
