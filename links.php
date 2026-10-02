<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

ob_start();

// Fetch all links
$stmt = $pdo->query("SELECT * FROM links ORDER BY category, sort_order, name");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$categories = [];
foreach ($links as $link) {
    $categories[$link['category']][] = $link;
}
?>

<h2>Links</h2>

<?php foreach ($categories as $category => $items): ?>
    <div class="dw-panel">
        <h3 class="dw-panel-title"><?= htmlspecialchars($category); ?></h3>

        <ul class="dw-link-list">
            <?php foreach ($items as $item): ?>
                <li class="dw-link">
                    <div class="dw-link-row">

                        <!-- Link Name -->
                        <a href="<?= htmlspecialchars($item['url']); ?>" target="_blank">
                            <?= htmlspecialchars($item['name']); ?>
                        </a>

                        <!-- Inline Description (HTML allowed) -->
                        <?php if (!empty($item['description'])): ?>
                            <div class="dw-link-desc">
                                <?= $item['description']; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
