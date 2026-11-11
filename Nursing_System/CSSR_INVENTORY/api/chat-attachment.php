<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/chat-attachments.php';
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$name = $_GET['file'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? (current_user()['shared_user_id'] ?? 0));
if (!is_string($name) || !preg_match('/^[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/D', $name) || $userId <= 0) { http_response_code(404); exit; }
try {
    $attachment = cssrChatAttachmentRecord(db(), $name, $userId);
    if (!$attachment) { http_response_code(404); exit; }
    $directory = realpath(__DIR__.'/../uploads/chat');
    $path = $directory ? realpath($directory.DIRECTORY_SEPARATOR.$name) : false;
    if (!$path || dirname($path) !== $directory || !is_file($path)) { http_response_code(404); exit; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) { http_response_code(404); exit; }
    session_write_close();
    header('Content-Type: '.$mime); header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="'.$name.'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
} catch (Throwable $error) { log_error($error,'CHAT_ATTACHMENT'); http_response_code(500); }
