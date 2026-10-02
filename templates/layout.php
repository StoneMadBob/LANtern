<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';

$siteName = get_app_setting($pdo, 'site_name', 'LANtern');
// $content must be defined by the page that includes this file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/main.js" defer></script>
    <script src="/assets/js/dashboard.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"></script>
    <link href="https://cdn.jsdelivr.net/gh/tylerecouture/summernote-codeblock/summernote-codeblock.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/tylerecouture/summernote-codeblock/summernote-codeblock.min.js"></script>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>

</head>


<body class="dw-shell">
    <?php include 'header.php'; ?>

    <div class="dw-body">
        <?php include 'sidebar.php'; ?>

        <main class="dw-content">
            <?= $content ?>
        </main>
    </div>

    <?php include 'footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("textarea").forEach(el => {
        $(el).summernote({
            placeholder: 'Enter content...',
            tabsize: 2,
            height: 300,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'table', 'codeBlock']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });
    });
});
</script>

</body>
</html>
