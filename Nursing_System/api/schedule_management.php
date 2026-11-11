<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_management.php';
require_once __DIR__ . '/../includes/nursing_requests.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
try {
    $access = nsScheduleAccess($_SESSION);
    if (!$access['view']) throw new DomainException('Scheduling is available only to Super Admin, Head Nurse, Supervisor, Chief Nurse, and Assistant Chief Nurse.');
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
    $input = [];
    if ($method === 'POST') {
        requireCsrfToken();
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2000000) throw new InvalidArgumentException('Schedule request is too large.');
        $input = json_decode(file_get_contents('php://input'), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Invalid request body.');
    }
    $department = canonicalDepartment(($method === 'POST' ? $input : $_GET)['department'] ?? $access['department']);
    $month = (string) (($method === 'POST' ? $input : $_GET)['month'] ?? date('Y-m'));
    nsScheduleScope($access, $department);
    nsScheduleRequireCurrentMonth($month);
    nrSchema($conn);
    if ($method === 'POST') {
        $result = ($input['action']??'')==='historical' ? nsHistoricalSave($conn,$access,$department,$month,$input) : nsScheduleSave($conn, $access, $department, $month, $input);
    } elseif (($_GET['action'] ?? '') === 'availability') {
        if(!$access['edit'])throw new DomainException('Scheduler access required.');
        $master=nsScheduleStaff($conn,$access,$department);$selected=array_values(array_filter($master,fn($e)=>(int)$e['id']===(int)($_GET['employee_id']??0)));if(!$selected)throw new DomainException('Choose an eligible employee from the central masterlist.');$availability=nrScheduleRequests($conn,['staff'=>$selected,'requests'=>[]],$month);$result=['requests'=>$availability['requests'],'internalLeaveIndicators'=>$availability['internalLeaveIndicators'],'boundaries'=>nsScheduleRead($conn,$access,$department,$month,$selected)['boundaries']];
    } elseif (($_GET['action'] ?? '') === 'history') {
        if (!$access['edit']) throw new DomainException('Revision history is restricted to schedulers.');
        $result = nsScheduleQuery($conn, 'SELECT r.version,r.action,r.changed_by_name,r.remarks,r.changes_json,r.created_at FROM nursing_schedule_revisions r JOIN nursing_schedule_months m ON m.id=r.schedule_id WHERE m.department=? AND m.month_start=? ORDER BY r.version DESC LIMIT 100', [$department, nsScheduleMonth($month)])->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($result as &$revision) { $revision['changes'] = nsScheduleDecode($revision['changes_json']); unset($revision['changes_json']); }
        unset($revision);
    } else {
        $result = nsScheduleRead($conn, $access, $department, $month);
    }
    echo nsScheduleEncode(['success' => true, 'data' => $result]);
} catch (Throwable $error) {
    $expected = $error instanceof InvalidArgumentException || $error instanceof DomainException || $error instanceof JsonException || $error->getCode() === 409;
    http_response_code($error->getCode() === 409 ? 409 : ($error instanceof DomainException ? 403 : ($expected ? 422 : 500)));
    if (!$expected) error_log('Schedule management: ' . $error->getMessage());
    echo json_encode(['success' => false, 'error' => $expected ? $error->getMessage() : 'Unable to process the schedule. Please contact the administrator.']);
}
