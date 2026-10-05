<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_roles(['admin', 'editor']);
$csrfToken = csrf_token();

ob_start();

// Handle messages
$message = '';
$message_type = '';

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = ($_GET['type'] === 'error') ? 'error' : 'success';
}

/* -----------------------------------------------------------
   FETCH ALL LINKS
----------------------------------------------------------- */
$stmt = $pdo->query("SELECT * FROM links ORDER BY `group`, category, name");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------------------------------------
   BUILD GROUP → CATEGORY → ITEMS STRUCTURE
----------------------------------------------------------- */
$groups = [];

foreach ($links as $link) {
    $groupName = $link['group'];
    $categoryName = $link['category'];

    if (!isset($groups[$groupName])) {
        $groups[$groupName] = [];
    }

    if (!isset($groups[$groupName][$categoryName])) {
        $groups[$groupName][$categoryName] = [];
    }

    $groups[$groupName][$categoryName][] = $link;
}
?>

<h1>Manage Links</h1>

<?php if (!empty($message)): ?>
    <div class="alert <?= $message_type; ?>">
        <?= $message; ?>
    </div>
<?php endif; ?>

<p>
    <a href="add_links.php" class="dw-btn dw-btn-primary">➕ Add New Link</a>
</p>


<?php foreach ($groups as $groupName => $categories): ?>
    <h2><?= htmlspecialchars($groupName) ?></h2>

    <?php foreach ($categories as $categoryName => $items): ?>
        <h3><?= htmlspecialchars($categoryName) ?></h3>

        <table class="dw-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Host</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['url']) ?></td>
                        <td><?= htmlspecialchars($item['host']) ?></td>
                        <td>
                            <a href="edit_links.php?id=<?= $item['id'] ?>" class="dw-btn dw-btn-edit">✏️ Edit</a>
                            <form action="delete_links.php" method="post" onsubmit="return confirm('Delete this link?');">
                                <input type="hidden" name="id" value="<?= (int) $item['id']; ?>">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="dw-btn dw-btn-delete">🗑️ Delete</button>
                            </form>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endforeach; ?>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
