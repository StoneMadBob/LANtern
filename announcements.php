<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/sanitize.php';

ob_start();

// Fetch category options and validate archive controls.
$categoryStmt = $pdo->query("SELECT DISTINCT category FROM announcements ORDER BY category ASC");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
$selectedCategory = trim($_GET['category'] ?? '');
if (!in_array($selectedCategory, $categories, true)) {
    $selectedCategory = '';
}

$sortOptions = [
    'newest' => 'Newest first',
    'oldest' => 'Oldest first',
    'category' => 'Category'
];
$sort = $_GET['sort'] ?? 'newest';
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}
$orderBy = [
    'newest' => 'created_at DESC, id DESC',
    'oldest' => 'created_at ASC, id ASC',
    'category' => 'category ASC, created_at DESC, id DESC'
][$sort];

$sql = 'SELECT * FROM announcements';
if ($selectedCategory !== '') {
    $sql .= ' WHERE category = ?';
}
$sql .= ' ORDER BY ' . $orderBy;
$stmt = $pdo->prepare($sql);
$stmt->execute($selectedCategory === '' ? [] : [$selectedCategory]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Announcements</h2>

<form method="get" class="dw-form dw-announcement-filters">
    <div>
        <label for="announcement-category">Category</label>
        <select id="announcement-category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" <?= $selectedCategory === $category ? 'selected' : ''; ?>><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="announcement-sort">Sort by</label>
        <select id="announcement-sort" name="sort">
            <?php foreach ($sortOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $sort === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="dw-btn dw-btn-primary">Apply</button>
    <?php if ($selectedCategory !== '' || $sort !== 'newest'): ?>
        <a href="announcements.php" class="dw-btn dw-btn-edit">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($announcements)): ?>

    <p>No announcements yet.</p>

<?php else: ?>

    <?php foreach ($announcements as $a): ?>
        <div class="dw-announcement">
            <h3><?php echo htmlspecialchars($a['title']); ?></h3>
            <small>Category: <?php echo htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8'); ?></small>
            <p><?= sanitize_allowed_html($a['body']); ?></p>
            <small>Posted: <?php echo htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
