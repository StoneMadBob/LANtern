<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';
#
require_roles(['admin', 'editor', 'author']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_kb.php');
    exit;
}

ob_start();

$article_id = (int)$_GET['id'];
$errors = [];
$success = false;

$stmt = $pdo->prepare("SELECT * FROM kb_articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    header('Location: manage_kb.php');
    exit;
}

$tag_stmt = $pdo->prepare("
    SELECT t.name
    FROM kb_tags t
    JOIN kb_article_tags at ON t.id = at.tag_id
    WHERE at.article_id = ?
");
$tag_stmt->execute([$article_id]);
$existing_tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
$existing_tags_str = implode(', ', $existing_tags);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content  = sanitize_allowed_html(trim($_POST['content'] ?? ''));
    $tags_raw = trim($_POST['tags'] ?? '');

    if ($title === '' || $category === '' || $content === '') {
        $errors[] = "All fields except tags are required.";
    }

    if (empty($errors)) {

        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
        $slug = trim($slug, '-');

        $update_stmt = $pdo->prepare("
            UPDATE kb_articles
            SET title = ?, slug = ?, category = ?, content = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([
            $title,
            $slug,
            $category,
            $content,
            $article_id
        ]);

        $pdo->prepare("DELETE FROM kb_article_tags WHERE article_id = ?")
            ->execute([$article_id]);

        if ($tags_raw !== '') {
            $tags = array_filter(array_map('trim', explode(',', $tags_raw)));

            foreach ($tags as $tag) {

                $tag_lookup = $pdo->prepare("SELECT id FROM kb_tags WHERE name = ?");
                $tag_lookup->execute([$tag]);
                $tag_id = $tag_lookup->fetchColumn();

                if (!$tag_id) {
                    $insert_tag = $pdo->prepare("INSERT INTO kb_tags (name) VALUES (?)");
                    $insert_tag->execute([$tag]);
                    $tag_id = $pdo->lastInsertId();
                }

                $link_stmt = $pdo->prepare("
                    INSERT IGNORE INTO kb_article_tags (article_id, tag_id)
                    VALUES (?, ?)
                ");
                $link_stmt->execute([$article_id, $tag_id]);
            }
        }

        $success = true;

        $tag_stmt->execute([$article_id]);
        $existing_tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
        $existing_tags_str = implode(', ', $existing_tags);
    }
}
?>

<h1>Edit KB Article</h1>

<?php if (!empty($errors)): ?>
    <div class="dw-alert dw-alert-danger">
        <?= implode('<br>', array_map('htmlspecialchars', $errors)); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="dw-alert dw-alert-success">
        Article updated successfully.
    </div>
<?php endif; ?>

<form method="post" class="dw-form">

    <label>Title</label>
    <input type="text" name="title" class="dw-input"
           value="<?= htmlspecialchars($article['title']); ?>" required>

    <label>Category</label>
    <input type="text" name="category" class="dw-input"
           value="<?= htmlspecialchars($article['category']); ?>" required>

    <label>Content (Markdown supported)</label>
    <textarea name="content" class="dw-textarea" rows="15" required><?=
        htmlspecialchars($article['content']);
    ?></textarea>

    <label>Tags (comma-separated)</label>
    <input type="text" name="tags" class="dw-input"
           value="<?= htmlspecialchars($existing_tags_str); ?>">

    <button type="submit" class="dw-btn dw-btn-primary">✏️ Save Changes</button>
</form>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
