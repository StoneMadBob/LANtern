<?php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf'] ?? '')) {
	http_response_code(403);
	exit('Invalid request token.');
}

logout();
