<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/settings.php';
require_once 'includes/captcha.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

function login_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$username = '';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$turnstileSiteKey = get_app_setting($pdo, 'turnstile_site_key');
$turnstileSecretKey = get_app_setting($pdo, 'turnstile_secret_key');

$pdo->prepare(
    'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 15 MINUTE)'
)->execute();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $attempts = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND username = ? '
        . 'AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)'
    );
    $attempts->execute([$ipAddress, $username]);
    $failedAttempts = (int)$attempts->fetchColumn();
    $captchaRequired = $failedAttempts >= 3;

    if (!hash_equals($_SESSION['login_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'The login form expired. Please try again.';
    } elseif ($failedAttempts >= 10) {
        $error = 'Too many failed login attempts. Please try again later.';
    } elseif ($captchaRequired && (
        $turnstileSiteKey === '' ||
        $turnstileSecretKey === '' ||
        !verify_turnstile($turnstileSecretKey, $_POST['cf-turnstile-response'] ?? '', $ipAddress)
    )) {
        $error = 'Please complete the CAPTCHA challenge.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            $clearAttempts = $pdo->prepare(
                'DELETE FROM login_attempts WHERE ip_address = ? AND username = ?'
            );
            $clearAttempts->execute([$ipAddress, $username]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $redirect = $_GET['redirect'] ?? '';
            if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
                header('Location: ' . $redirect);
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }

        $recordAttempt = $pdo->prepare(
            'INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)'
        );
        $recordAttempt->execute([$ipAddress, $username]);
        $error = 'Invalid username or password.';
        $failedAttempts++;
        $captchaRequired = $failedAttempts >= 3;
    }
} else {
    $failedAttempts = 0;
    $captchaRequired = false;
}

ob_start();
?>
<h2>Login</h2>

<?php if (!empty($_GET['redirect'])): ?>
    <p class="dw-info">Please log in to continue.</p>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?= login_escape($error); ?></div>
<?php endif; ?>

<form method="post" class="dw-form">
    <input type="hidden" name="csrf" value="<?= login_escape($_SESSION['login_csrf']); ?>">

    <label for="username">Username</label>
    <input id="username" type="text" name="username" value="<?= login_escape($username); ?>" required autocomplete="username">

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">

    <?php if ($captchaRequired): ?>
        <?php if ($turnstileSiteKey): ?>
            <div class="cf-turnstile" data-sitekey="<?= login_escape($turnstileSiteKey); ?>"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php else: ?>
            <p class="alert error">Login CAPTCHA is not configured.</p>
        <?php endif; ?>
    <?php endif; ?>

    <button type="submit" class="dw-btn dw-btn-primary">Login</button>
</form>

<p><a href="signup.php">Create an account</a></p>
<?php
$content = ob_get_clean();
include 'templates/layout.php';