<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_roles(['admin', 'editor']);
$csrfToken = csrf_token();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $id = filter_var($_POST['delete'], FILTER_VALIDATE_INT);

    // Get file path
    if ($id !== false) {
        $stmt = $pdo->prepare("SELECT path FROM uploads WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // Delete file from disk
            if (file_exists("../" . $file['path'])) {
                unlink("../" . $file['path']);
            }

            // Delete DB entry
            $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    header('Location: manage_uploads.php');
    exit;
}

// Fetch uploads
$stmt = $pdo->query("SELECT * FROM uploads ORDER BY uploaded_at DESC");
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
$extensions = [];
foreach ($uploads as $upload) {
    $extension = strtolower(pathinfo($upload['path'], PATHINFO_EXTENSION));
    if (preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
        $extensions[$extension] = $extension;
    }
}
sort($extensions);

$typeFilter = $_GET['type'] ?? 'all';
if (!is_string($typeFilter) || !in_array($typeFilter, ['all', 'images', 'documents'], true)) {
    $typeFilter = 'all';
}
$extensionFilter = $_GET['extension'] ?? '';
if (!is_string($extensionFilter)) {
    $extensionFilter = '';
}
$extensionFilter = strtolower($extensionFilter);
if ($extensionFilter !== '' && !in_array($extensionFilter, $extensions, true)) {
    $extensionFilter = '';
}

$uploads = array_values(array_filter($uploads, static function ($upload) use ($typeFilter, $extensionFilter) {
    $extension = strtolower(pathinfo($upload['path'], PATHINFO_EXTENSION));
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true);
    $matchesType = $typeFilter === 'all'
        || ($typeFilter === 'images' && $isImage)
        || ($typeFilter === 'documents' && !$isImage);
    $matchesExtension = $extensionFilter === '' || $extension === $extensionFilter;

    return $matchesType && $matchesExtension;
}));

$content = '<h2>Manage Uploads</h2>';
$content .= '<form method="get" class="dw-upload-filters">';
$content .= '<label for="upload-type">Type</label>';
$content .= '<select id="upload-type" name="type" class="dw-filter-select">';
foreach (['all' => 'All types', 'images' => 'Images', 'documents' => 'Documents'] as $value => $label) {
    $selected = $typeFilter === $value ? ' selected' : '';
    $content .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
}
$content .= '</select>';
$content .= '<label for="upload-extension">Extension</label>';
$content .= '<select id="upload-extension" name="extension" class="dw-filter-select">';
$content .= '<option value="">All extensions</option>';
foreach ($extensions as $extension) {
    $selected = $extensionFilter === $extension ? ' selected' : '';
    $label = '.' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8');
    $content .= '<option value="' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . $label . '</option>';
}
$content .= '</select>';
$content .= '<button type="submit" class="dw-btn dw-btn-primary">Filter</button>';
$content .= '<a href="manage_uploads.php" class="dw-btn dw-btn-edit">Clear</a>';
$content .= '</form>';
$content .= '<table class="dw-table dw-uploads-table">

<tr>
    <th>Preview</th>
    <th>Filename</th>
    <th>Link</th>
    <th>Uploaded</th>
    <th>Edit</th>
    <th>Delete</th>
</tr>
';

foreach ($uploads as $u) {
    $path = htmlspecialchars($u['path'], ENT_QUOTES, 'UTF-8');
    $filename = htmlspecialchars($u['filename'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($u['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $uploadedAt = htmlspecialchars($u['uploaded_at'], ENT_QUOTES, 'UTF-8');
    $extension = strtolower(pathinfo($u['path'], PATHINFO_EXTENSION));
    $safeExtension = htmlspecialchars($extension, ENT_QUOTES, 'UTF-8');
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true);
    $id = (int) $u['id'];
    $preview = $isImage
        ? "<img src='../{$path}' alt='{$filename}'>"
        : "<span class='dw-upload-type'>{$safeExtension}</span>";
    $descriptionMarkup = $description !== ''
        ? "<small class='dw-upload-description'>{$description}</small>"
        : '';
    $content .= "
    <tr>
        <td>{$preview}</td>
        <td>{$filename}{$descriptionMarkup}</td>
        <td><a href='../{$path}'>Open upload</a></td>
        <td>{$uploadedAt}</td>
        <td><a href='edit_upload.php?id={$id}' class='dw-btn dw-btn-edit'>✏️ Edit</a></td>
        <td>
            <form method='post' action='manage_uploads.php' onsubmit='return confirm(&quot;Delete this upload?&quot;);'>
                <input type='hidden' name='csrf' value='{$csrfToken}'>
                <button type='submit' name='delete' value='{$id}' class='dw-btn dw-btn-delete'>🗑️ Delete</button>
            </form>
        </td>
    </tr>
    ";
}

$content .= '</table>';

include '../templates/layout.php';
