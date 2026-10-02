<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_roles(['admin', 'editor', 'author']);
$csrfToken = csrf_token();

ob_start();

// Fetch all articles
$stmt = $pdo->query("
    SELECT a.id, a.title, a.category, a.content, a.updated_at,
           GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS tags
    FROM kb_articles a
    LEFT JOIN kb_article_tags at ON at.article_id = a.id
    LEFT JOIN kb_tags t ON t.id = at.tag_id
    GROUP BY a.id, a.title, a.category, a.content, a.updated_at
    ORDER BY updated_at DESC
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Knowledge Base</h1>

<p>
    <a href="add_kb.php" class="dw-btn dw-btn-primary">➕ Add Article</a>
</p>

<div class="dw-kb-grid">
<?php foreach ($articles as $kb): ?>
    <div class="dw-kb-card">

        <div class="dw-kb-header">
            <strong><?= htmlspecialchars($kb['title']); ?></strong>
        </div>

        <div class="dw-kb-meta">
            <?= htmlspecialchars($kb['category']); ?> • Updated <?= htmlspecialchars($kb['updated_at']); ?>
        </div>

        <?php if (!empty($kb['tags'])): ?>
            <div class="dw-kb-tags">
                <?php foreach (explode(',', $kb['tags']) as $tag): ?>
                    <span class="dw-kb-tag"><?= htmlspecialchars(trim($tag)); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dw-kb-preview">
            <?= htmlspecialchars(strip_tags(sanitize_allowed_html($kb['content']))); ?>…
        </div>

        <div class="dw-kb-actions">
            <a href="edit_kb.php?id=<?= $kb['id']; ?>" class="dw-btn dw-btn-edit">
                ✏️ Edit
            </a>

            <?php if (in_array($_SESSION['role'], ['admin', 'editor'], true)): ?>
                <form action="delete_kb.php" method="post" onsubmit="return confirm('Delete this article?');">
                    <input type="hidden" name="id" value="<?= (int) $kb['id']; ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="dw-btn dw-btn-delete">🗑️ Delete</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
<?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
