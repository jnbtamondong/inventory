<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Render the real launcher template with synthetic permissions, without DB access.
$canAccessNursing = $canAccessCssr = $canAccessReports = $canAccessProcurement = $canAccessSchedule = $isAdmin = true;
if (($argv[1] ?? '') === 'staff') { $isAdmin = $canAccessProcurement = false; }
$role = 'SYSTEM_ADMIN';
$nursingHome = 'dashboard.php';
$_SESSION = ['first_name' => 'Preview'];
function roleLabel($role) { return 'System Administrator'; }
$source = file_get_contents(__DIR__ . '/../pages/system_menu.php');
$source = substr($source, strpos($source, '<!DOCTYPE html>'));
eval('?>' . $source);
