<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf'] ?? '')) {
     http_response_code(403);
     exit('Invalid request token.');
}

/* -----------------------------------------------------------
    GET DEVICE ID
----------------------------------------------------------- */
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: manage_devices.php?msg=Invalid device ID&type=error");
    exit;
}

/* -----------------------------------------------------------
   CHECK DEVICE EXISTS
----------------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: manage_devices.php?msg=Device not found&type=error");
    exit;
}

/* -----------------------------------------------------------
   DELETE DEVICE
----------------------------------------------------------- */
$stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");

if ($stmt->execute([$id])) {
    header("Location: manage_devices.php?msg=Device deleted successfully&type=success");
    exit;
} else {
    header("Location: manage_devices.php?msg=Failed to delete device&type=error");
    exit;
}
