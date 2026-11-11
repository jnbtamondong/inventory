<?php
declare(strict_types=1);
function cssrChatAttachmentRecord(PDO $db, mixed $name, int $userId): ?array
{
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/D', $name) || $userId <= 0) return null;
    $stmt=$db->prepare('SELECT attachment_path FROM chat_messages WHERE attachment_path=? AND (sender_user_id=? OR receiver_user_id=?) LIMIT 1');
    $stmt->execute([$name,$userId,$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
