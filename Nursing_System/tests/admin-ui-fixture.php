<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// The production template and its controllers run against synthetic, read-only data.
$_SESSION = ['first_name' => 'Preview', 'role' => 'SUPER_ADMIN', 'user_id' => 1];
function roleLabel($role) { return $role === 'SUPER_ADMIN' ? 'System Administrator' : 'Nurse'; }
$error = $success = '';
$cssrWorkspace = false;
$roleOptions = $staffRoleOptions = ['NURSE' => 'Nurse', 'HEAD_NURSE' => 'Head Nurse'];
$departmentOptions = ['NSO' => 'NSO', 'ICU' => 'ICU', 'DR' => 'Delivery Room'];
$jobTitleOptions = ['NURSE I' => 'NURSE I', 'NURSE II' => 'NURSE II'];
$departmentCount = 3; $jobTitleCount = $roleCount = 2;
$activeUserCount = 1; $inactiveUserCount = 0; $passwordResetRequests = [];
$users = [['user_id' => 2, 'first_name' => 'Preview', 'last_name' => 'Nurse', 'username' => 'preview.nurse',
    'employee_id' => 'PREVIEW-001', 'email' => '', 'position' => 'NURSE II', 'role' => 'NURSE', 'department' => 'ICU',
    'employment_status' => 'PERMANENT', 'date_hired' => '2026-01-01', 'status' => 'ACTIVE', 'is_active' => 1, 'phone' => '', 'updated_at' => '2026-10-05']];
$source = file_get_contents(__DIR__ . '/../pages/admin_users.php');
$start = strpos($source, '<?php if($cssrWorkspace):');
if ($start === false) throw new RuntimeException('Account template boundary not found.');
$source = substr($source, $start);
eval('?>' . $source);
