<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

require_login();

ob_start();

// Fetch all articles
$stmt = $pdo->query("
    SELECT id, title, slug, category, updated_at
    FROM kb_articles
    ORDER BY category ASC, title ASC
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$categories = [];
foreach ($articles as $a) {
    $categories[$a['category']][] = $a;
}

// Fetch tags for each article
$tag_stmt = $pdo->prepare("
    SELECT t.name
    FROM kb_tags t
    JOIN kb_article_tags at ON t.id = at.tag_id
    WHERE at.article_id = ?
");
?>

<h1>Knowledge Base</h1>

<?php if (empty($articles)): ?>
    <p>No KB articles have been created yet.</p>
<?php else: ?>

    <?php foreach ($categories as $category => $items): ?>
        <h2><?= htmlspecialchars($category); ?></h2>

        <ul class="dw-link-list">
            <?php foreach ($items as $a): ?>
                <?php
                $tag_stmt->execute([$a['id']]);
                $tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <li class="dw-link">
                    <a href="kb_article.php?id=<?= $a['id']; ?>">
                        <?= htmlspecialchars($a['title']); ?>
                    </a>

                    <?php if (!empty($tags)): ?>
                        <span class="dw-small">
                            <?= htmlspecialchars(implode(', ', $tags)); ?>
                        </span>
                    <?php endif; ?>

                    <span class="dw-small">
                        Updated: <?= htmlspecialchars($a['updated_at']); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
