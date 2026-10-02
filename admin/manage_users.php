<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_admin();

if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}

function users_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$currentUserId = (int)$_SESSION['user_id'];
$roles = ['admin', 'editor', 'author', 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['users_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $username)) {
                    throw new RuntimeException('Usernames may contain only letters, numbers, dots, underscores, and hyphens.');
                }
                if (strlen($password) < 12) {
                    throw new RuntimeException('Passwords must be at least 12 characters long.');
                }
                if (!in_array($role, $roles, true)) {
                    throw new RuntimeException('Invalid role selected.');
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
                );
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                $message = 'User added successfully.';
            } elseif ($action === 'update') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $role = $_POST['role'] ?? '';
                $password = $_POST['password'] ?? '';

                if ($userId < 1 || !in_array($role, $roles, true)) {
                    throw new RuntimeException('Invalid user update.');
                }
                if ($userId === $currentUserId && $role !== 'admin') {
                    throw new RuntimeException('You cannot remove your own administrator role.');
                }

                $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new RuntimeException('User not found.');
                }

                if ($user['role'] === 'admin' && $role !== 'admin') {
                    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                    if ($adminCount <= 1) {
                        throw new RuntimeException('The site must keep at least one administrator.');
                    }
                }

                if ($password !== '' && strlen($password) < 12) {
                    throw new RuntimeException('Passwords must be at least 12 characters long.');
                }

                if ($password !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET role = ?, password_hash = ? WHERE id = ?'
                    );
                    $stmt->execute([$role, password_hash($password, PASSWORD_DEFAULT), $userId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                    $stmt->execute([$role, $userId]);
                }

                if ($userId === $currentUserId) {
                    $_SESSION['role'] = $role;
                }
                $message = 'User updated successfully.';
            } elseif ($action === 'delete') {
                $userId = (int)($_POST['user_id'] ?? 0);

                if ($userId < 1 || $userId === $currentUserId) {
                    throw new RuntimeException('You cannot delete your own account.');
                }

                $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new RuntimeException('User not found.');
                }

                if ($user['role'] === 'admin') {
                    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                    if ($adminCount <= 1) {
                        throw new RuntimeException('The site must keep at least one administrator.');
                    }
                }

                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $message = 'User deleted successfully.';
            }
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'That username is already in use.'
                : 'The user could not be saved.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$users = $pdo->query(
    'SELECT id, username, role, created_at FROM users ORDER BY username'
)->fetchAll();

ob_start();
?>
<h1>Manage Users</h1>

<?php if ($message): ?>
    <div class="alert success"><?= users_escape($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?= users_escape($error); ?></div>
<?php endif; ?>

<section class="dw-panel">
    <h2>Add User</h2>
    <form method="post" class="dw-form">
        <input type="hidden" name="csrf" value="<?= users_escape($_SESSION['users_csrf']); ?>">
        <input type="hidden" name="action" value="add">

        <label for="username">Username</label>
        <input id="username" name="username" maxlength="50" required>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required>

        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="user">User</option>
            <option value="editor">Editor</option>
            <option value="author">Author</option>
            <option value="admin">Administrator</option>
        </select>

        <button type="submit" class="dw-btn dw-btn-primary">Add User</button>
    </form>
</section>

<h2>Existing Users</h2>
<div class="dw-user-table-wrap">
    <table class="dw-table dw-user-table">
        <colgroup>
            <col class="dw-user-col-name">
            <col class="dw-user-col-role">
            <col class="dw-user-col-created">
            <col class="dw-user-col-actions">
            <col class="dw-user-col-delete">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">Username</th>
                <th scope="col">Role</th>
                <th scope="col">Created</th>
                <th scope="col">Role / Password</th>
                <th scope="col">Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= users_escape($user['username']); ?></td>
                    <td><?= users_escape(ucfirst($user['role'])); ?></td>
                    <td><?= users_escape($user['created_at']); ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= users_escape($_SESSION['users_csrf']); ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>">
                            <select name="role" aria-label="Role for <?= users_escape($user['username']); ?>">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= users_escape($role); ?>" <?= $user['role'] === $role ? 'selected' : ''; ?>>
                                        <?= users_escape(ucfirst($role)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="password" name="password" minlength="12" autocomplete="new-password" placeholder="New password" aria-label="New password for <?= users_escape($user['username']); ?>">
                            <button type="submit" class="dw-btn dw-btn-edit">Save</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="csrf" value="<?= users_escape($_SESSION['users_csrf']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>">
                            <button type="submit" class="dw-btn dw-btn-delete" <?= (int)$user['id'] === $currentUserId ? 'disabled' : ''; ?>>Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
include '../templates/layout.php';