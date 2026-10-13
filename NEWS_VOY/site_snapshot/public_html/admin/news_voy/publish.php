<?php
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/news_db.php';

session_start();
validateAdminSession();
if (!userHasPermission(3)) {
    header('Location: ' . website_base_url . 'admin/access_denied.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['voy_news_publish_csrf']) ||
    !hash_equals($_SESSION['voy_news_publish_csrf'], $csrf)) {
    http_response_code(403);
    exit('Invalid request token');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$destination = filter_var($_POST['destination'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1 || !in_array($destination, [0, 1], true)) {
    http_response_code(400);
    exit('Invalid article or destination');
}

try {
    global $conn;
    $stmt = $conn->prepare('UPDATE voy_news SET is_public = ? WHERE id = ? AND is_public = 2');
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('ii', $destination, $id);
    $stmt->execute();
    $updated = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$updated) {
        http_response_code(409);
        exit('Draft not found or already published');
    }

    header('Location: ' . website_base_url . 'admin/news_voy/index.php?published=1');
    exit();
} catch (Throwable $e) {
    error_log('news_voy publish.php: ' . $e->getMessage());
    http_response_code(500);
    exit('Publication failed');
}
