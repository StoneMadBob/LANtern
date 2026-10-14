<?php
session_start();

$configPath = __DIR__ . '/includes/config.php';
$installLockPath = __DIR__ . '/includes/install.lock';
$guardPath = __DIR__ . '/includes/.install.guard';
$schemaPath = __DIR__ . '/sql/schema.sql';
$migrationDirectory = __DIR__ . '/sql/migrations';
$errors = [];
$success = false;
$successType = 'Installation';
$migrationStates = [];
$migrationTableExists = false;
$guardHandle = null;
$configExists = is_file($configPath);
$mode = $configExists ? 'upgrade' : (is_file($installLockPath) ? 'locked' : 'install');

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function quote_mysql_identifier($value) {
    return '`' . str_replace('`', '``', $value) . '`';
}

function connect_to_database($host, $name, $user, $password) {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';

    try {
        return new PDO($dsn, $user, $password, $options);
    } catch (PDOException $exception) {
        $mysqlErrorCode = $exception->errorInfo[1] ?? null;
        if ((int)$mysqlErrorCode !== 1049) {
            throw $exception;
        }

        $serverPdo = new PDO(
            'mysql:host=' . $host . ';charset=utf8mb4',
            $user,
            $password,
            $options
        );
        $serverPdo->exec(
            'CREATE DATABASE IF NOT EXISTS ' . quote_mysql_identifier($name)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        return new PDO($dsn, $user, $password, $options);
    }
}

function table_exists(PDO $pdo, $tableName) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$tableName]);
    return (int)$statement->fetchColumn() > 0;
}

function column_exists(PDO $pdo, $tableName, $columnName) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $statement->execute([$tableName, $columnName]);
    return (int)$statement->fetchColumn() > 0;
}

function migration_files($directory) {
    $files = glob($directory . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9]_*.sql');
    sort($files, SORT_STRING);
    return $files;
}

function migration_effect_exists(PDO $pdo, $migration) {
    switch (pathinfo($migration, PATHINFO_FILENAME)) {
        case '001_add_editor_author_roles':
            if (!column_exists($pdo, 'users', 'role')) {
                return false;
            }
            $statement = $pdo->query(
                "SELECT column_type FROM information_schema.columns "
                . "WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'"
            );
            $columnType = (string)$statement->fetchColumn();
            return strpos($columnType, "'admin'") !== false
                && strpos($columnType, "'editor'") !== false
                && strpos($columnType, "'author'") !== false
                && strpos($columnType, "'user'") !== false;
        case '002_add_signup_rate_limiting':
            return table_exists($pdo, 'signup_attempts') && table_exists($pdo, 'settings');
        case '003_add_login_rate_limiting':
            return table_exists($pdo, 'login_attempts');
        case '004_add_device_active_issues_toggle':
            return column_exists($pdo, 'devices', 'ignore_active_issues');
        case '005_add_upload_description':
            return column_exists($pdo, 'uploads', 'description');
        case '006_rename_default_site':
            return false;
        case '007_add_announcement_categories':
            return column_exists($pdo, 'announcements', 'category');
        case '008_add_ping_history':
            return table_exists($pdo, 'ping_results');
        case '009_add_speedtest_history':
            return table_exists($pdo, 'speedtest_results');
        case '010_add_speedtest_test_type':
            return column_exists($pdo, 'speedtest_results', 'test_type');
        default:
            return false;
    }
}

function get_migration_states(PDO $pdo, $directory, &$hasMigrationTable) {
    $hasMigrationTable = table_exists($pdo, 'schema_migrations');
    $appliedMigrations = [];

    if ($hasMigrationTable) {
        $appliedMigrations = $pdo->query('SELECT migration FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    $states = [];
    foreach (migration_files($directory) as $file) {
        $name = basename($file);
        $states[$name] = in_array($name, $appliedMigrations, true)
            || migration_effect_exists($pdo, $name);
    }

    return $states;
}

function create_migration_table(PDO $pdo) {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'migration VARCHAR(190) NOT NULL PRIMARY KEY, '
        . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function record_migration(PDO $pdo, $migration) {
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (migration) VALUES (?)'
    );
    $statement->execute([$migration]);
}

function run_migrations(PDO $pdo, $directory, $states) {
    create_migration_table($pdo);

    foreach (migration_files($directory) as $file) {
        $name = basename($file);
        if (!empty($states[$name])) {
            record_migration($pdo, $name);
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Could not read migration ' . $name . '.');
        }

        $statements = preg_split('/;\s*(?:\R|$)/', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        record_migration($pdo, $name);
    }
}

function write_install_lock($path) {
    if (file_put_contents($path, "LANtern installation completed\n", LOCK_EX) === false) {
        throw new RuntimeException('The installation lock file could not be written.');
    }
}

function write_config($path, $host, $name, $user, $password) {
    $config = "<?php\n\n";
    $config .= "// Generated by install.php. Keep this file outside source control.\n";
    $config .= 'define(\'DB_HOST\', ' . var_export($host, true) . ");\n";
    $config .= 'define(\'DB_NAME\', ' . var_export($name, true) . ");\n";
    $config .= 'define(\'DB_USER\', ' . var_export($user, true) . ");\n";
    $config .= 'define(\'DB_PASS\', ' . var_export($password, true) . ");\n\n";

    $temporaryConfig = $path . '.tmp-' . bin2hex(random_bytes(8));
    if (file_put_contents($temporaryConfig, $config, LOCK_EX) === false
        || !rename($temporaryConfig, $path)) {
        @unlink($temporaryConfig);
        throw new RuntimeException('The configuration file could not be written.');
    }
}

$form = [
    'db_host' => 'localhost',
    'db_name' => 'pernhome',
    'db_user' => 'pernhome',
    'site_name' => 'LANtern',
    'admin_username' => 'admin',
    'turnstile_site_key' => '',
    'turnstile_secret_key' => ''
];

if ($mode === 'upgrade') {
    try {
        require_once $configPath;
        $pdo = connect_to_database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
        $migrationStates = get_migration_states($pdo, $migrationDirectory, $migrationTableExists);
    } catch (PDOException | RuntimeException $exception) {
        $errors[] = 'Could not inspect the existing installation: ' . $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'locked') {
        $errors[] = 'The installation lock exists, but the database configuration is missing. Restore includes/config.php before continuing.';
    } elseif (!is_string($_POST['csrf'] ?? null)
        || !hash_equals($_SESSION['install_csrf'], $_POST['csrf'])) {
        $errors[] = 'The installation form expired. Please try again.';
    } else {
        $guardHandle = fopen($guardPath, 'c');
        if ($guardHandle === false || !flock($guardHandle, LOCK_EX | LOCK_NB)) {
            $errors[] = 'Another install or upgrade request is in progress. Please try again.';
        } elseif ($mode === 'upgrade') {
            $successType = 'Upgrade';
            try {
                require_once $configPath;
                $pdo = connect_to_database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
                $migrationStates = get_migration_states($pdo, $migrationDirectory, $migrationTableExists);
                run_migrations($pdo, $migrationDirectory, $migrationStates);
                write_install_lock($installLockPath);
                $success = true;
            } catch (PDOException | RuntimeException $exception) {
                $errors[] = 'The upgrade did not complete: ' . $exception->getMessage();
            }
        } elseif ($mode === 'install') {
            foreach ($form as $key => $value) {
                $submittedValue = $_POST[$key] ?? $value;
                $form[$key] = is_string($submittedValue) ? trim($submittedValue) : $value;
            }

            $dbPassword = is_string($_POST['db_password'] ?? null) ? $_POST['db_password'] : '';
            $adminPassword = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
            $adminPasswordConfirm = is_string($_POST['admin_password_confirm'] ?? null)
                ? $_POST['admin_password_confirm']
                : '';

            if ($form['db_host'] === '' || $form['db_name'] === '' || $form['db_user'] === '') {
                $errors[] = 'Database host, name, and user are required.';
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $form['db_name'])) {
                $errors[] = 'Database name may contain only letters, numbers, and underscores.';
            }
            if ($form['site_name'] === '' || $form['admin_username'] === '') {
                $errors[] = 'Site name and admin username are required.';
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $form['admin_username'])) {
                $errors[] = 'Admin username may contain only letters, numbers, dots, underscores, and hyphens.';
            }
            if (strlen($adminPassword) < 12) {
                $errors[] = 'The admin password must be at least 12 characters long.';
            } elseif ($adminPassword !== $adminPasswordConfirm) {
                $errors[] = 'The admin passwords do not match.';
            }

            if (!$errors) {
                try {
                    $pdo = connect_to_database(
                        $form['db_host'],
                        $form['db_name'],
                        $form['db_user'],
                        $dbPassword
                    );
                    $tableCount = (int)$pdo->query(
                        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
                    )->fetchColumn();
                    if ($tableCount > 0) {
                        throw new RuntimeException(
                            'The selected database already contains tables. No changes were made. '
                            . 'Use the existing includes/config.php to run an upgrade, or choose an empty database.'
                        );
                    }

                    $schema = file_get_contents($schemaPath);
                    if ($schema === false) {
                        throw new RuntimeException('The database schema could not be read.');
                    }
                    $statements = preg_split('/;\s*(?:\R|$)/', $schema);
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if ($statement !== '') {
                            $pdo->exec($statement);
                        }
                    }

                    $settingInsert = $pdo->prepare(
                        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) '
                        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                    );
                    $settingInsert->execute(['site_name', $form['site_name']]);
                    $settingInsert->execute(['turnstile_site_key', $form['turnstile_site_key']]);
                    $settingInsert->execute(['turnstile_secret_key', $form['turnstile_secret_key']]);

                    $userInsert = $pdo->prepare(
                        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, \'admin\')'
                    );
                    $userInsert->execute([
                        $form['admin_username'],
                        password_hash($adminPassword, PASSWORD_DEFAULT)
                    ]);

                    create_migration_table($pdo);
                    foreach (migration_files($migrationDirectory) as $file) {
                        record_migration($pdo, basename($file));
                    }

                    write_config($configPath, $form['db_host'], $form['db_name'], $form['db_user'], $dbPassword);
                    $mode = 'upgrade';
                    write_install_lock($installLockPath);
                    $success = true;
                } catch (PDOException | RuntimeException $exception) {
                    $errors[] = 'The installation did not complete: ' . $exception->getMessage();
                }
            }
        }
    }
}

$pendingMigrationCount = count(array_filter($migrationStates, function ($applied) {
    return !$applied;
}));
$needsUpgradeCheck = $mode === 'upgrade'
    && (!$migrationTableExists || !is_file($installLockPath));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install LANtern</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="dw-shell">
    <main class="dw-content">
        <h1>LANtern Setup</h1>

        <?php if ($success): ?>
            <div class="alert success">
                <?= e($successType); ?> completed. Remove install.php from the web server before using the site.
            </div>
            <p><a href="/login.php" class="dw-btn dw-btn-primary">Go to login</a></p>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= e($error); ?></div>
            <?php endforeach; ?>

            <?php if ($mode === 'locked'): ?>
                <div class="alert error">
                    This installation is locked against a fresh install. Restore the existing database configuration to run upgrades.
                </div>
            <?php elseif ($mode === 'upgrade'): ?>
                <p>An existing LANtern configuration was found. The current database will be checked for unapplied migrations.</p>
                <?php if (!$errors): ?>
                    <?php if ($pendingMigrationCount > 0): ?>
                        <h2>Pending database updates</h2>
                        <ul>
                            <?php foreach ($migrationStates as $migration => $applied): ?>
                                <?php if (!$applied): ?>
                                    <li><?= e($migration); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No pending database migrations were detected.</p>
                    <?php endif; ?>

                    <p>Back up the database before continuing. LANtern will preserve existing records and apply only migrations not already detected or recorded.</p>
                    <?php if ($pendingMigrationCount > 0 || $needsUpgradeCheck): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['install_csrf']); ?>">
                            <button type="submit" class="dw-btn dw-btn-primary">
                                <?= $pendingMigrationCount > 0 ? 'Apply database updates' : 'Record migration status'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <p>Enter database details and the first administrator account. Fresh installs require an empty database; existing data will not be replaced.</p>

                <form method="post" class="dw-form">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['install_csrf']); ?>">

                    <h2>Database</h2>
                    <label for="db_host">Database host</label>
                    <input id="db_host" name="db_host" value="<?= e($form['db_host']); ?>" required>

                    <label for="db_name">Database name</label>
                    <input id="db_name" name="db_name" value="<?= e($form['db_name']); ?>" required>

                    <label for="db_user">Database user</label>
                    <input id="db_user" name="db_user" value="<?= e($form['db_user']); ?>" required>

                    <label for="db_password">Database password</label>
                    <input id="db_password" type="password" name="db_password" required>

                    <h2>Site administrator</h2>
                    <label for="site_name">Site name</label>
                    <input id="site_name" name="site_name" value="<?= e($form['site_name']); ?>" required>

                    <label for="admin_username">Admin username</label>
                    <input id="admin_username" name="admin_username" value="<?= e($form['admin_username']); ?>" required>

                    <label for="admin_password">Admin password</label>
                    <input id="admin_password" type="password" name="admin_password" minlength="12" required>

                    <label for="admin_password_confirm">Confirm admin password</label>
                    <input id="admin_password_confirm" type="password" name="admin_password_confirm" minlength="12" required>

                    <h2>Signup Protection</h2>
                    <label for="turnstile_site_key">Cloudflare Turnstile site key</label>
                    <input id="turnstile_site_key" name="turnstile_site_key" value="<?= e($form['turnstile_site_key']); ?>">

                    <label for="turnstile_secret_key">Cloudflare Turnstile secret key</label>
                    <input id="turnstile_secret_key" type="password" name="turnstile_secret_key" value="<?= e($form['turnstile_secret_key']); ?>">

                    <button type="submit" class="dw-btn dw-btn-primary">Install</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
<?php
if (is_resource($guardHandle)) {
    flock($guardHandle, LOCK_UN);
    fclose($guardHandle);
}
