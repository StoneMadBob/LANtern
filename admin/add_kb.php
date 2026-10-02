<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/sanitize.php';

require_roles(['admin', 'editor', 'author']);

ob_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content  = sanitize_allowed_html($_POST['content'] ?? '');

    if ($title === '' || $category === '' || $content === '') {
        $error = "All fields are required.";
    } else {

        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $title), '-'));
        if ($slug === '') {
            $slug = 'article';
        }

        $base_slug = $slug;
        $suffix = 2;
        $slug_stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_articles WHERE slug = ?");
        while (true) {
            $slug_stmt->execute([$slug]);
            if ((int)$slug_stmt->fetchColumn() === 0) {
                break;
            }
            $slug = $base_slug . '-' . $suffix++;
        }

        $stmt = $pdo->prepare("
            INSERT INTO kb_articles (title, slug, category, content, updated_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        if ($stmt->execute([$title, $slug, $category, $content])) {
            $success = "Article added successfully.";
        } else {
            $error = "Database error — unable to save article.";
        }
    }
}
?>

<h2>Add Knowledge Base Article</h2>

<?php if ($error): ?>
    <p class="dw-error"><?= htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p class="dw-success"><?= htmlspecialchars($success); ?></p>
<?php endif; ?>

<form method="POST">

    <label>Title</label><br>
    <input type="text" name="title" required style="width:100%;" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"><br><br>

    <label>Category</label><br>
    <input type="text" name="category" required style="width:100%;" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"><br><br>

    <label>Content</label><br>
    <textarea name="content" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea><br><br>

    <button type="submit" class="dw-btn dw-btn-primary">➕ Add Article</button>
</form>

<?php
$content = ob_get_clean();
include '../templates/layout.php';
