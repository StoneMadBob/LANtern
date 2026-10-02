<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/sanitize.php';

require_login();

ob_start();

$id = intval($_GET['id']);

$stmt = $pdo->prepare("
    SELECT title, content, category, updated_at
    FROM kb_articles
    WHERE id = ?
");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo "<h2>Article not found</h2>";
    $content = ob_get_clean();
    include 'templates/layout.php';
    exit;
}

$articleHtml = sanitize_allowed_html($article['content']);
$articleContent = $articleHtml;
$articleToc = [];

libxml_use_internal_errors(true);
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML('<?xml encoding="UTF-8">' . $articleHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$xpath = new DOMXPath($dom);
$headings = $xpath->query('//h1 | //h2 | //h3 | //h4');
$usedIds = [];

if ($headings !== false) {
    foreach ($headings as $heading) {
        /** @var DOMElement $headingElement */
        $headingElement = $heading;

        if (!$headingElement instanceof DOMElement) {
            continue;
        }

        $text = trim($headingElement->textContent);
        if ($text === '') {
            continue;
        }

        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            continue;
        }

        $base = $slug;
        $index = 2;
        while (isset($usedIds[$slug])) {
            $slug = $base . '-' . $index;
            $index++;
        }

        $usedIds[$slug] = true;
        $headingElement->setAttribute('id', $slug);

        $articleToc[] = [
            'title' => $text,
            'slug' => $slug,
            'level' => (int) preg_replace('/\D+/', '', $headingElement->nodeName),
        ];
    }

    $articleContent = $dom->saveHTML();
}
?>

<h1><?= htmlspecialchars($article['title']); ?></h1>

<p class="dw-small">
    Category: <?= htmlspecialchars($article['category']); ?><br>
    Updated: <?= htmlspecialchars($article['updated_at']); ?>
</p>

<div class="kb-article-layout">
    <?php if (!empty($articleToc)): ?>
        <button type="button" class="kb-toc-toggle" aria-expanded="false">Contents</button>
        <aside class="kb-article-toc">
            <h3>Contents</h3>
            <nav>
                <ul>
                    <?php foreach ($articleToc as $entry): ?>
                        <li class="kb-toc-level-<?= (int)$entry['level']; ?>">
                            <a href="#<?= htmlspecialchars($entry['slug'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
    <?php endif; ?>

    <article class="kb-article-body">
        <?= $articleContent; ?>
    </article>
</div>

<?php if (!empty($articleToc)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.querySelector('.kb-toc-toggle');
        const toc = document.querySelector('.kb-article-toc');

        if (!toggle || !toc) {
            return;
        }

        toggle.addEventListener('click', function () {
            const visible = toc.style.display === 'block';
            toc.style.display = visible ? 'none' : 'block';
            toggle.setAttribute('aria-expanded', String(!visible));
        });
    });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'templates/layout.php';
exit;