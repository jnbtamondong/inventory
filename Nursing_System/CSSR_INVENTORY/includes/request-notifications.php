<?php
function cssrCanReceiveRequestNotifications(array $user, callable $can): bool
{
    $role = strtoupper(trim((string) ($user['role'] ?? '')));
    $department = strtoupper(trim((string) ($user['department_name'] ?? $user['department'] ?? '')));
    return ($department === 'CSSR' || in_array($role, ['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF'], true))
        && $can('cssr_inventory','view')
        && ($can('cssr_inventory','edit') || $can('ris_requests','approve'));
}

function cssrRequestNotificationCounts(callable $value, bool $includeDepartmentRequests = true): array
{
    // Match the outstanding statuses in Pending Actions. Counts are requests, not item lines.
    $counts = [
        'department' => $includeDepartmentRequests ? (int) $value('SELECT COUNT(*) FROM item_requests WHERE status IN ("Pending","Approved")') : 0,
        'storage' => (int) $value('SELECT COUNT(*) FROM storage_requests WHERE status="Pending"'),
        'returns' => (int) $value('SELECT COUNT(*) FROM department_returns WHERE status="Pending"'),
    ];
    return $counts + ['total'=>array_sum($counts)];
}

function cssrCanReceiveChatNotifications(array $user, callable $can): bool
{
    $role = strtoupper(trim((string) ($user['role'] ?? '')));
    return $role !== '' && !in_array($role, ['ADMITTING_STAFF','CLERK'], true)
        && ($can('cssr_inventory','view') || $can('ris_requests','view') || $can('cssr_dashboard','view'));
}

function cssrChatUnreadCount(callable $value, int $sharedUserId): int
{
    if ($sharedUserId <= 0) return 0;
    return (int) $value('SELECT COUNT(*) FROM chat_messages WHERE receiver_user_id=? AND read_at IS NULL', [$sharedUserId]);
}

function cssrRequestNotificationBadge(bool $enabled, int $total, string $kind = 'request'): string
{
    if (!$enabled) return '';
    $kind = in_array($kind, ['request','chat','notification'], true) ? $kind : 'request';
    return '<span class="cu-request-count" data-cu-'.$kind.'-count aria-hidden="true"'.($total === 0 ? ' hidden' : '').'>'.($total > 99 ? '99+' : $total).'</span>';
}
