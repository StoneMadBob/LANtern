<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/settings.php';

require_admin();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings ('
    . 'setting_key VARCHAR(100) PRIMARY KEY, '
    . 'setting_value TEXT NOT NULL, '
    . 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)'
);

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

function settings_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$siteName = get_app_setting($pdo, 'site_name', 'LANtern');
$turnstileSiteKey = get_app_setting($pdo, 'turnstile_site_key');
$existingTurnstileSecretKey = get_app_setting($pdo, 'turnstile_secret_key');
$uptimeKumaUrl = get_app_setting($pdo, 'plugin_uptime_kuma_url');
$uptimeKumaSlug = get_app_setting($pdo, 'plugin_uptime_kuma_slug');
$proxmoxUrl = get_app_setting($pdo, 'plugin_proxmox_url');
$proxmoxTokenId = get_app_setting($pdo, 'plugin_proxmox_token_id');
$existingProxmoxTokenSecret = get_app_setting($pdo, 'plugin_proxmox_token_secret');
$validateIntegrationUrl = static function (string $url): bool {
    if ($url === '') {
        return true;
    }
    $parts = parse_url($url);
    return is_array($parts)
        && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !isset($parts['query'])
        && !isset($parts['fragment']);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['settings_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'The settings form expired. Please try again.';
    } else {
        $siteName = trim($_POST['site_name'] ?? $siteName);
        $turnstileSiteKey = trim($_POST['turnstile_site_key'] ?? $turnstileSiteKey);
        $turnstileSecretKey = trim($_POST['turnstile_secret_key'] ?? '');
        $uptimeKumaUrl = trim($_POST['uptime_kuma_url'] ?? $uptimeKumaUrl);
        $uptimeKumaSlug = trim($_POST['uptime_kuma_slug'] ?? $uptimeKumaSlug);
        $proxmoxUrl = trim($_POST['proxmox_url'] ?? $proxmoxUrl);
        $proxmoxTokenId = trim($_POST['proxmox_token_id'] ?? $proxmoxTokenId);
        $proxmoxTokenSecret = trim($_POST['proxmox_token_secret'] ?? '');

        if ($turnstileSecretKey === '') {
            $turnstileSecretKey = $existingTurnstileSecretKey;
        }
        if ($proxmoxTokenSecret === '' && empty($_POST['clear_proxmox_token_secret'])) {
            $proxmoxTokenSecret = $existingProxmoxTokenSecret;
        }

        if ($siteName === '') {
            $error = 'Site name is required.';
        } elseif (!$validateIntegrationUrl($uptimeKumaUrl) || !$validateIntegrationUrl($proxmoxUrl)) {
            $error = 'Integration URLs must be valid HTTP or HTTPS URLs without credentials, query strings, or fragments.';
        } elseif ($uptimeKumaSlug !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $uptimeKumaSlug)) {
            $error = 'Uptime Kuma status page slug may contain only letters, numbers, hyphens, and underscores.';
        } elseif (count(array_filter([$proxmoxUrl, $proxmoxTokenId, $proxmoxTokenSecret], static fn($value) => $value !== '')) !== 0 && count(array_filter([$proxmoxUrl, $proxmoxTokenId, $proxmoxTokenSecret], static fn($value) => $value !== '')) !== 3 && empty($_POST['clear_proxmox_token_secret'])) {
            $error = 'Enter the Proxmox URL, token ID, and token secret together.';
        } else {
            save_app_setting($pdo, 'site_name', $siteName);
            save_app_setting($pdo, 'turnstile_site_key', $turnstileSiteKey);
            save_app_setting($pdo, 'turnstile_secret_key', $turnstileSecretKey);
            save_app_setting($pdo, 'plugin_uptime_kuma_url', $uptimeKumaUrl);
            save_app_setting($pdo, 'plugin_uptime_kuma_slug', $uptimeKumaSlug);
            save_app_setting($pdo, 'plugin_proxmox_url', $proxmoxUrl);
            save_app_setting($pdo, 'plugin_proxmox_token_id', $proxmoxTokenId);
            save_app_setting($pdo, 'plugin_proxmox_token_secret', $proxmoxTokenSecret);
            $message = 'Settings saved successfully.';
        }
    }
}

ob_start();
?>
<h2>Admin Settings</h2>

<?php if ($message): ?>
    <div class="alert success"><?= settings_escape($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?= settings_escape($error); ?></div>
<?php endif; ?>

<p>Manage site-wide settings and signup protection.</p>

<section class="dw-panel">
    <button type="button" class="dw-panel-toggle">Site Information</button>
    <div class="dw-panel-body">
        <p><strong>Site Name:</strong> <?= settings_escape($siteName); ?></p>
        <p><strong>Database:</strong> <?= settings_escape(DB_NAME); ?></p>
        <p><strong>Host:</strong> <?= settings_escape(DB_HOST); ?></p>
        <p><strong>Admin User:</strong> <?= settings_escape($_SESSION['username']); ?></p>
    </div>
</section>

<section class="dw-panel">
    <h3>Application Settings</h3>
    <form method="post" class="dw-form">
        <input type="hidden" name="csrf" value="<?= settings_escape($_SESSION['settings_csrf']); ?>">

        <label for="site_name">Site name</label>
        <input id="site_name" type="text" name="site_name" value="<?= settings_escape($siteName); ?>" required>

        <label for="turnstile_site_key">Turnstile site key</label>
        <input id="turnstile_site_key" type="text" name="turnstile_site_key" value="<?= settings_escape($turnstileSiteKey); ?>">

        <label for="turnstile_secret_key">Turnstile secret key</label>
        <input id="turnstile_secret_key" type="password" name="turnstile_secret_key" placeholder="Leave blank to keep the current key">

        <button type="submit" class="dw-btn dw-btn-primary">Save Settings</button>
    </form>
</section>

<section class="dw-panel">
    <h3>Plugins</h3>
    <form method="post" class="dw-form">
        <input type="hidden" name="csrf" value="<?= settings_escape($_SESSION['settings_csrf']); ?>">

        <h4>Uptime Kuma</h4>
        <label for="uptime_kuma_url">Instance URL</label>
        <input id="uptime_kuma_url" type="url" name="uptime_kuma_url" value="<?= settings_escape($uptimeKumaUrl); ?>" placeholder="https://status.example.com">
        <label for="uptime_kuma_slug">Public status page slug</label>
        <input id="uptime_kuma_slug" type="text" name="uptime_kuma_slug" value="<?= settings_escape($uptimeKumaSlug); ?>" placeholder="homelab">

        <h4>Proxmox</h4>
        <label for="proxmox_url">API URL</label>
        <input id="proxmox_url" type="url" name="proxmox_url" value="<?= settings_escape($proxmoxUrl); ?>" placeholder="https://proxmox.example.com:8006">
        <label for="proxmox_token_id">API token ID</label>
        <input id="proxmox_token_id" type="text" name="proxmox_token_id" value="<?= settings_escape($proxmoxTokenId); ?>" placeholder="user@realm!tokenid">
        <label for="proxmox_token_secret">API token secret</label>
        <input id="proxmox_token_secret" type="password" name="proxmox_token_secret" placeholder="<?= $existingProxmoxTokenSecret !== '' ? 'Saved; leave blank to keep it' : 'Required for Proxmox status'; ?>" autocomplete="new-password">
        <?php if ($existingProxmoxTokenSecret !== ''): ?>
            <label><input type="checkbox" name="clear_proxmox_token_secret" value="1"> Clear saved Proxmox token secret</label>
        <?php endif; ?>

        <button type="submit" class="dw-btn dw-btn-primary">Save Plugin Settings</button>
    </form>
</section>

<section class="dw-panel">
    <button type="button" class="dw-panel-toggle">Future Options</button>
    <div class="dw-panel-body">
        <ul>
            <li>Theme selection</li>
            <li>Dashboard widgets</li>
            <li>System status integration</li>
            <li>Uptime Kuma API key</li>
            <li>Custom categories</li>
        </ul>
    </div>
</section>

<section class="dw-panel">
    <button type="button" class="dw-panel-toggle">Debug Tools</button>
    <div class="dw-panel-body">
        <p>PHP Version: <?= settings_escape(phpversion()); ?></p>
        <p>Server Software: <?= settings_escape($_SERVER['SERVER_SOFTWARE'] ?? 'Unavailable'); ?></p>
        <p>Document Root: <?= settings_escape($_SERVER['DOCUMENT_ROOT'] ?? 'Unavailable'); ?></p>
    </div>
</section>
<?php
$content = ob_get_clean();
include '../templates/layout.php';
