<?php
require_once __DIR__.'/../includes/nursing_requests.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Render the real page template without a session, DB connection, or HTTP endpoint.
function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$personal = in_array('--personal', $argv, true);
$scheduleAccess = ['edit' => !$personal, 'approve' => !$personal, 'all' => !$personal, 'view' => true, 'user_id' => $personal ? 12 : 1, 'department' => 'ER'];
$scheduleDepartments = ['ER' => 'Emergency Room', 'WARD_3F' => 'Medical / Surgery Ward'];
$scheduleDepartment = 'ER'; $scheduleMonth = '2026-10'; $scheduleCurrentMonth='2026-10';
$scheduleBoot = ['access' => $scheduleAccess, 'department' => $scheduleDepartment, 'month' => $scheduleMonth, 'currentMonth'=>$scheduleCurrentMonth, 'csrf' => 'synthetic-test-token'];
$_SESSION = ['first_name' => 'Fixture'];
$source = file_get_contents(__DIR__ . '/../pages/schedule_management.php');
$source = substr($source, strpos($source, '<!doctype html>'));
$sidebar = '<aside class="sidebar nursing-sidebar"><div class="sidebar-brand"><img class="nursing-sidebar-logo" src="../public/images/branding/jvgh_seal.png" alt="JVGH seal"><div class="nursing-sidebar-brand-copy"><strong>NURSYNC</strong><small>Nursing Service</small></div><button type="button" class="sidebar-toggle" aria-label="Collapse navigation"></button></div><nav class="menu"><a href="#"><i class="bi bi-grid"></i><span>Dashboard</span></a><a href="#"><i class="bi bi-people"></i><span>Nursing Employees</span></a></nav></aside>';
$source = str_replace(["<?php include __DIR__ . '/../includes/sidebar.php'; ?>","<?php include __DIR__ . '/../includes/schedule_sidebar.php'; ?>"], $sidebar, $source);
eval('?>' . $source);
