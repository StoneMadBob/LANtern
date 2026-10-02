<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/uploads.php';
require_roles(['admin', 'editor']);

$message = '';
$messageType = 'error';
$uploadedPath = '';
$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$maxFileSize = 5 * 1024 * 1024;

if (empty($_SESSION['upload_csrf'])) {
    $_SESSION['upload_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['upload_csrf'], $_POST['csrf'] ?? '')) {
        $message = 'The upload form expired. Please try again.';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload failed. Please choose a valid file.';
    } else {
        $file = $_FILES['file'];
        $description = $_POST['description'] ?? '';
        $description = is_string($description) ? upload_plain_text_description($description) : '';

        if (strlen($description) > 4000) {
            $message = 'Descriptions must be 4,000 characters or fewer.';
        } elseif ($file['size'] > $maxFileSize) {
            $message = 'Files must be 5 MB or smaller.';
        } elseif (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
            $message = 'The upload directory could not be created.';
        } elseif (!is_writable($uploadDirectory)) {
            $message = 'The upload directory is not writable by the web server.';
        } else {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo ? finfo_file($fileInfo, $file['tmp_name']) : false;
            if ($fileInfo) {
                finfo_close($fileInfo);
            }

            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'text/plain' => 'txt',
                'text/csv' => 'csv',
                'application/csv' => 'csv'
            ];

            if (!isset($extensions[$mimeType])) {
                $message = 'This file type is not allowed. Choose an image, PDF, DOCX, XLSX, PPTX, TXT, or CSV file.';
            } else {
                $extension = $extensions[$mimeType];
                $filename = upload_display_name($file['name'], $extension);
                $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
                $relativePath = 'uploads/' . $storedFilename;
                $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedFilename;

                if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO uploads (filename, path, description) VALUES (?, ?, ?)");
                        $stmt->execute([$filename, $relativePath, $description]);

                        $message = 'Upload successful.';
                        $messageType = 'success';
                        $uploadedPath = $relativePath;
                    } catch (PDOException $exception) {
                        @unlink($absolutePath);
                        $message = 'The upload could not be recorded.';
                    }
                } else {
                    $message = 'Failed to save the uploaded file.';
                }
            }
        }
    }
}

ob_start();
?>
<h2>Upload File</h2>

<?php if ($message): ?>
    <div class="alert <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($uploadedPath !== ''): ?>
            <a href="<?= htmlspecialchars($uploadedPath, ENT_QUOTES, 'UTF-8'); ?>">View uploaded file</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="dw-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['upload_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>Select File</label><br>
    <input type="file" name="file" accept="image/jpeg,image/png,image/gif,.pdf,.docx,.xlsx,.pptx,.txt,.csv" required><br><br>
    <label for="upload-description">Description (optional)</label>
    <textarea id="upload-description" name="description" maxlength="4000" rows="3"></textarea>
    <small>JPG, PNG, GIF, PDF, DOCX, XLSX, PPTX, TXT, or CSV. Maximum size: 5 MB.</small><br><br>
    <button type="submit" class="dw-btn dw-btn-primary">Upload</button>
</form>

<?php
$content = ob_get_clean();

include 'templates/layout.php';
