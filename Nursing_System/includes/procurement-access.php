<?php
// Shared workspace rule for both the menu and Procurement's server-side entry point.
function canAccessProcurementWorkspace(array $identity): bool
{
    $role = strtoupper(trim((string) ($identity['role'] ?? '')));
    $department = strtoupper(trim((string) ($identity['department_name'] ?? $identity['department'] ?? '')));
    if ($role === '') return false;
    return $department === 'CSSR' || in_array($role, [
        'SUPER_ADMIN', 'CSSR_HEAD', 'CSSR_STAFF', 'HEAD_NURSE',
        'SUPERVISOR_NURSE', 'ASSISTANT_CHIEF_NURSE', 'CHIEF_NURSE',
    ], true);
}
