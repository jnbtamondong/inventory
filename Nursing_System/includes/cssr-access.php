<?php
// A department-specific capability; never promotes the hospital account role.
function isCssrAttendantAdmin(array $identity): bool
{
    $role = strtoupper(trim((string) ($identity['original_role'] ?? $identity['role'] ?? '')));
    $department = strtoupper(trim((string) ($identity['department_name'] ?? $identity['department'] ?? '')));
    return $role === 'NURSING_ATTENDANT' && $department === 'CSSR';
}

function cssrAttendantPermission(array $identity, string $module, string $action, bool $inventoryWorkspace = false): bool
{
    if (!isCssrAttendantAdmin($identity) || !in_array($action, ['view','create','edit','delete','approve','print'], true)) return false;
    $modules = ['cssr_dashboard','cssr_inventory','ris_requests'];
    // Reports are also used outside CSSR, so scope this explicitly. User activity stays admin-only.
    if ($inventoryWorkspace) $modules[] = 'reports';
    return in_array($module, $modules, true);
}

function cssrCanEditTransactionDate(array $identity): bool
{
    $role = strtoupper(trim((string) ($identity['role'] ?? '')));
    $department = strtoupper(trim((string) ($identity['department_name'] ?? $identity['department'] ?? '')));
    return in_array($role, ['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF'], true) || $department === 'CSSR';
}

function cssrTransactionToday(?DateTimeImmutable $now = null): string
{
    return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
}

function cssrTransactionDate(array $identity, mixed $submitted, ?DateTimeImmutable $now = null): string
{
    // Always use the server's Philippine date for department users, regardless of submitted values.
    if (!cssrCanEditTransactionDate($identity)) return cssrTransactionToday($now);
    $value = is_string($submitted) && !str_contains($submitted, "\0") ? trim($submitted) : '';
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila')) : false;
    if (!$date || $date->format('Y-m-d') !== $value || (int) substr($value, 0, 4) < 1000) {
        throw new InvalidArgumentException('Enter a valid request or return date.');
    }
    return $value;
}
