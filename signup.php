<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/settings.php';
require_once 'includes/captcha.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

function signup_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = false;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['signup_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'The signup form expired. Please try again.';
    } else {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $pdo->prepare(
                'DELETE FROM signup_attempts WHERE attempted_at < (NOW() - INTERVAL 1 HOUR)'
            )->execute();

            $attempts = $pdo->prepare(
                'SELECT COUNT(*) FROM signup_attempts WHERE ip_address = ? AND attempted_at >= (NOW() - INTERVAL 1 HOUR)'
            );
            $attempts->execute([$ipAddress]);

            if ((int)$attempts->fetchColumn() >= 5) {
                $error = 'Too many signup attempts. Please try again later.';
            } else {
                $pdo->prepare('INSERT INTO signup_attempts (ip_address) VALUES (?)')->execute([$ipAddress]);

                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $passwordConfirm = $_POST['password_confirm'] ?? '';
                $captchaToken = $_POST['cf-turnstile-response'] ?? '';
                $siteKey = get_app_setting($pdo, 'turnstile_site_key');
                $secretKey = get_app_setting($pdo, 'turnstile_secret_key');

                if ($siteKey === '' || $secretKey === '') {
                    $error = 'Signup is not configured yet. Please contact the administrator.';
                } elseif ($captchaToken === '' || !verify_turnstile($secretKey, $captchaToken, $ipAddress)) {
                    $error = 'Please complete the CAPTCHA challenge.';
                } elseif (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $username)) {
                    $error = 'Username may contain only letters, numbers, dots, underscores, and hyphens.';
                } elseif (strlen($password) < 12) {
                    $error = 'Password must be at least 12 characters long.';
                } elseif ($password !== $passwordConfirm) {
                    $error = 'Passwords do not match.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, \'user\')'
                    );
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                    $success = true;
                }
            }
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'That username is already in use.'
                : 'The account could not be created.';
        }
    }
}

$turnstileSiteKey = get_app_setting($pdo, 'turnstile_site_key');

ob_start();
?>
<h2>Create Account</h2>

<?php if ($success): ?>
    <div class="alert success">Your account was created. You can now log in.</div>
    <p><a href="login.php" class="dw-btn dw-btn-primary">Go to login</a></p>
<?php else: ?>
    <?php if ($error): ?>
        <div class="alert error"><?= signup_escape($error); ?></div>
    <?php endif; ?>

    <form method="post" class="dw-form">
        <input type="hidden" name="csrf" value="<?= signup_escape($_SESSION['signup_csrf']); ?>">

        <label for="username">Username</label>
        <input id="username" type="text" name="username" maxlength="50" value="<?= signup_escape($username); ?>" required autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="12" required autocomplete="new-password">

        <label for="password_confirm">Confirm password</label>
        <input id="password_confirm" type="password" name="password_confirm" minlength="12" required autocomplete="new-password">

        <?php if ($turnstileSiteKey): ?>
            <div class="cf-turnstile" data-sitekey="<?= signup_escape($turnstileSiteKey); ?>"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php else: ?>
            <p class="alert error">Signup CAPTCHA is not configured.</p>
        <?php endif; ?>

        <button type="submit" class="dw-btn dw-btn-primary">Create Account</button>
    </form>
<?php endif; ?>
<?php
$content = ob_get_clean();
include 'templates/layout.php';