<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';

$siteName = get_app_setting($pdo, 'site_name', 'LANtern');

function dw_breadcrumbs() {
    $path = $_SERVER['REQUEST_URI'];          // /admin/manage_kb.php
    $parts = explode('/', trim($path, '/'));  // ['admin', 'manage_kb.php']

    $breadcrumbs = [];
    $url = '';

    foreach ($parts as $part) {
        $url .= '/' . $part;

        // Clean names
        $name = ucfirst(str_replace(['.php', '_'], ['', ' '], $part));

        $breadcrumbs[] = "<a href='{$url}'>{$name}</a>";
    }

    return implode(" <span class='dw-bc-sep'>›</span> ", $breadcrumbs);
}

?>
<header class="dw-header">
    <div class="dw-logo">
        <img src="/assets/images/logo.png" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
        <h1><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <div class="dw-user-info">
<?php if (isset($_SESSION['user_id'])): ?>
    <span class="dw-user-info">
        Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?>
    </span>
    <form action="/logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="dw-btn dw-btn-delete dw-header-btn">Logout</button>
    </form>
<?php else: ?>
   <a href="/login.php" class="dw-btn dw-btn-primary dw-header-btn">Login</a>
<?php endif; ?>
    </div>
</header>

<div class="dw-breadcrumbs">
    <?= dw_breadcrumbs(); ?>
</div>