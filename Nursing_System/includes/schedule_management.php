<?php
// Schedule storage is separate from attendance, procedures, and employee documents.
const NS_SCHEDULE_TARGET = 160;
require_once __DIR__.'/schedule_policy.php';

function nsScheduleCurrentMonth(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m'); }
function nsScheduleRequireCurrentMonth(string $month): void {
    nsScheduleMonth($month);
    if ($month < nsScheduleCurrentMonth()) throw new DomainException('Monthly Scheduling allows only the current and future months.');
}

function nsScheduleAccess(array $session): array
{
    $role = strtoupper(trim((string)($session['role'] ?? '')))==='ASSISTANT_CHIEF_NURSE'
        ? 'ASSISTANT_CHIEF_NURSE' : canonicalRole($session['role'] ?? '');
    $all = in_array($role, ['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'ASSISTANT_CHIEF_NURSE'], true);
    return ['all' => $all, 'edit' => $all || $role === 'HEAD_NURSE', 'approve' => $all,
        'view' => $all || $role === 'HEAD_NURSE',
        'user_id' => (int) ($session['user_id'] ?? 0), 'department' => canonicalDepartment($session['department'] ?? '')];
}

function nsScheduleQuery(mysqli $conn, string $sql, array $params = []): mysqli_stmt
{
    $statement = $conn->prepare($sql);
    if ($params) $statement->bind_param(str_repeat('s', count($params)), ...$params);
    $statement->execute();
    return $statement;
}

function nsScheduleSchema(mysqli $conn): void
{
    nsPolicySchema($conn);
    $conn->query("CREATE TABLE IF NOT EXISTS nursing_schedule_months (
        id INT AUTO_INCREMENT PRIMARY KEY, department VARCHAR(50) NOT NULL, month_start DATE NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'DRAFT', version INT NOT NULL DEFAULT 1,
        draft_json LONGTEXT NOT NULL, final_json LONGTEXT NULL, final_status VARCHAR(30) NULL,
        final_version INT NULL, updated_by INT NOT NULL, submitted_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_schedule_unit_month(department,month_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS nursing_schedule_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY, schedule_id INT NOT NULL, version INT NOT NULL,
        action VARCHAR(30) NOT NULL, changed_by INT NOT NULL, changed_by_name VARCHAR(160) NOT NULL,
        remarks VARCHAR(1000) NOT NULL, payload_json LONGTEXT NOT NULL, changes_json LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_schedule_revision(schedule_id,version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function nsScheduleMonth(string $month): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month);
    if (!$date || $date->format('Y-m') !== $month || $month < '2000-01' || $month > '2100-12') {
        throw new InvalidArgumentException('Choose a month between 2000 and 2100.');
    }
    return $month . '-01';
}

function nsScheduleDepartments(): array
{
    $departments=['ER' => 'Emergency Room', 'WARD_3F' => 'Medical / Surgery Ward', 'WARD_2F' => 'OB / Pedia Ward',
        'NURSING_TRAINING' => 'Nursing Training', 'OPD' => 'Outpatient Department', 'OR' => 'Operating Room', 'LR_DR' => 'Labor / Delivery Room',
        'ICU' => 'MICU', 'NICU' => 'Neonatal Intensive Care Unit', 'ASC' => 'Ambulatory Surgery',
        'CSSR' => 'CSSR', 'NURSING' => 'Nursing Service', 'NSO' => 'Nursing Service Office', 'PHU' => 'Public Health Unit', 'ONCOLOGY' => 'Oncology'];
    global $conn;if(isset($conn)&&$conn instanceof mysqli){$table=nsScheduleQuery($conn,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_departments'")->get_result()->fetch_assoc();if($table)foreach(nsScheduleQuery($conn,"SELECT code,name FROM user_departments WHERE status='ACTIVE'")->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$departments[canonicalDepartment($row['code'])]=$row['name'];}return $departments;
}

function nsScheduleScope(array $access, string $department): void
{
    if (!$access['view'] || $department === '' || strlen($department) > 50 ||
        (!$access['all'] && $department !== $access['department'])) {
        throw new DomainException('You do not have access to this unit.');
    }
}

function nsScheduleStaff(mysqli $conn, array $access, string $department): array
{
    // Alias normalization follows the existing authentication helper.
    $params = [];
    $where = '';
    if (!$access['edit']) { $where = ' WHERE user_id=?'; $params[] = $access['user_id']; }
    $rows = nsScheduleQuery($conn, "SELECT * FROM users$where ORDER BY last_name,first_name", $params)->get_result()->fetch_all(MYSQLI_ASSOC);
    $staff = [];
    foreach ($rows as $row) {
        if (!naEligible($row) || !(int) $row['is_active'] || $row['status'] !== 'ACTIVE') continue;
        if (canonicalDepartment($row['department']) !== canonicalDepartment($department)) continue;
        $policy = nsScheduleQuery($conn,'SELECT * FROM nursing_schedule_employee_policy WHERE employee_id=?',[$row['user_id']])->get_result()->fetch_assoc();
        if($policy && !(int)$policy['scheduling_active'])continue;
        $category = nsScheduleCategory($row);
        $staff[] = ['id' => (int) $row['user_id'], 'name' => naName($row), 'position' => trim((string) ($row['position'] ?? '')), 'employmentStatus'=>$row['employment_status']??'', 'category' => $category, 'staffingRole' => $category === 'NA' ? 'NA' : 'NURSE',
            'fixedShift'=>$policy['fixed_shift']??'8', 'shiftEligibility'=>json_decode($policy['shift_eligibility']??'null',true)??[], 'department'=>canonicalDepartment($row['department']), 'role'=>naRole($row), 'pattern' => '8', 'group' => '', 'preference' => 'ANY', 'active' => true];
    }
    return nsScheduleSortEmployees($staff);
}

function nsScheduleSortEmployees(array $staff): array
{
    usort($staff, static function ($a, $b) {
        $category = (($a['category'] ?? '') === 'NA' ? 1 : 0) <=> (($b['category'] ?? '') === 'NA' ? 1 : 0);
        return $category ?: strcasecmp($a['name'] ?? '', $b['name'] ?? '') ?: (($a['id'] ?? 0) <=> ($b['id'] ?? 0));
    });
    return $staff;
}

function nsScheduleCategory(array $employee): string
{
    return preg_match('/\b(nurs(?:e|ing)\s+attendant|NA)\b/i', str_replace('_', ' ', (string) ($employee['position'] ?? '') . ' ' . (string) ($employee['role'] ?? ''))) ? 'NA' : 'NURSE';
}

function nsScheduleDuties(): array
{
    return ['6'=>8,'7'=>8,'8'=>8,'M'=>10,'PM8'=>8,'PH'=>0,'SPL'=>0,'SL'=>0,'VL'=>0,'WL'=>0,'FL'=>0,'ML'=>0,'PL'=>0,'SOLO_PARENT'=>0,'OTHER_LEAVE'=>0,'AM' => 8, 'PM' => 8, 'NIGHT8' => 8, 'DAY12' => 12, 'NIGHT12' => 12, 'ADJUST8' => 8,
        'OFF' => 0, 'REQUEST_OFF' => 0, 'LEAVE' => 0, 'TRAINING' => 0, 'OFFICIAL_BUSINESS' => 0];
}

function nsScheduleEmpty(array $staff): array
{
    $coverage = [];
    foreach (nsScheduleDuties() as $duty => $hours) if ($hours) $coverage[$duty] = ['min' => 0, 'max' => count($staff)];
    return ['staff' => $staff, 'assignments' => [], 'requests' => [], 'mode' => 'MANUAL',
        'rules' => ['minRest' => 12, 'maxConsecutive' => 6, 'minOff' => 1, 'maxOff' => 31, 'offPer7' => 1, 'reviewed' => true, 'staffingTargets' => ['TRIAGE' => 0, 'NURSE' => 0, 'NA' => 0], 'staffingShifts' => [], 'coverage' => $coverage]];
}

function nsScheduleDecode(?string $json): ?array
{
    return $json === null ? null : json_decode($json, true, 64, JSON_THROW_ON_ERROR);
}

function nsScheduleEncode($value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

function nsScheduleInt($value, int $min, int $max, string $label): int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $min || (int) $value > $max) {
        throw new InvalidArgumentException("Invalid $label.");
    }
    return (int) $value;
}

function nsScheduleValidate(array $input, array $master, ?array $previous, array $access, string $month): array
{
    $previousRequests=array_map(fn($request)=>['employee_id'=>(int)$request['employee_id'],'date'=>$request['date'],'type'=>$request['type'],'remarks'=>trim($request['remarks']??''),'status'=>$request['status']],$previous['requests']??[]);
    $allowed = array_column($master, null, 'id');
    foreach ($previous['staff'] ?? [] as $employee) if (!isset($allowed[$employee['id']])) {
        $employee['active'] = false;
        $allowed[$employee['id']] = $employee;
    }
    $staff = []; $seen = [];
    if (!is_array($input['staff'] ?? null) || count($input['staff']) > 200) throw new InvalidArgumentException('A roster can contain up to 200 employees.');
    foreach ($input['staff'] as $employee) {
        $id = nsScheduleInt($employee['id'] ?? 0, 1, PHP_INT_MAX, 'employee');
        if (!isset($allowed[$id]) || isset($seen[$id])) throw new DomainException('The roster contains an unauthorized or duplicate employee.');
        $pattern = (string) ($employee['pattern'] ?? '');
        $preference = (string) ($employee['preference'] ?? 'ANY');
        if (!in_array($pattern, ['8', '12'], true) || !in_array($preference, ['ANY', 'AM', 'PM', 'NIGHT8', 'DAY12', 'NIGHT12'], true)) throw new InvalidArgumentException('Invalid duty pattern or shift preference.');
        if (($pattern === '8' && in_array($preference, ['DAY12', 'NIGHT12'], true)) || ($pattern === '12' && in_array($preference, ['AM', 'PM', 'NIGHT8'], true))) throw new InvalidArgumentException('Shift preference does not match the duty pattern.');
        $group = trim((string) ($employee['group'] ?? ''));
        if (strlen($group) > 50) throw new InvalidArgumentException('Group names must be 50 characters or fewer.');
        $category = $allowed[$id]['category'] ?? nsScheduleCategory($allowed[$id]);
        $staffingRole = $employee['staffingRole'] ?? ($category === 'NA' ? 'NA' : 'NURSE');
        if (!in_array($staffingRole, $category === 'NA' ? ['NA'] : ['NURSE', 'TRIAGE'], true)) throw new InvalidArgumentException('The staffing role must match the employee position.');
        $staff[] = array_merge($allowed[$id], ['category' => $category, 'staffingRole' => $staffingRole, 'pattern' => $pattern, 'group' => $group, 'preference' => $preference]);
        $seen[$id] = true;
    }
    $validDate = static function ($date) use ($month): bool {
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        return $parsed && $parsed->format('Y-m-d') === $date && substr($date, 0, 7) === $month;
    };
    $assignments = [];
    if (!is_array($input['assignments'] ?? null)) throw new InvalidArgumentException('Invalid assignments.');
    foreach ($input['assignments'] as $id => $days) {
        if (!isset($seen[$id]) || !is_array($days) || count($days) > 31) throw new InvalidArgumentException('Invalid employee assignments.');
        foreach ($days as $date => $duty) {
            if (!$validDate($date) || !is_string($duty) || !array_key_exists($duty, nsScheduleDuties())) throw new InvalidArgumentException('Invalid assignment date or duty.');
            $assignments[$id][$date] = $duty;
        }
    }
    $requests = []; $requestKeys = [];
    if (!is_array($input['requests'] ?? null) || count($input['requests']) > 6200) throw new InvalidArgumentException('Invalid requests.');
    foreach ($input['requests'] as $request) {
        $id = nsScheduleInt($request['employee_id'] ?? 0, 1, PHP_INT_MAX, 'request employee');
        $date = $request['date'] ?? '';
        $type = $request['type'] ?? '';
        $status = $request['status'] ?? '';
        $remarks = trim((string) ($request['remarks'] ?? ''));
        $key = $id . ':' . $date;
        if (!isset($seen[$id]) || !$validDate($date) || isset($requestKeys[$key]) ||
            !in_array($type, ['REQUEST_OFF', 'LEAVE', 'TRAINING', 'OFFICIAL_BUSINESS'], true) ||
            !in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true) || strlen($remarks) > 500) throw new InvalidArgumentException('Invalid or duplicate absence request.');
        $normalized = ['employee_id' => $id, 'date' => $date, 'type' => $type, 'remarks' => $remarks, 'status' => $status];
        if($type==='REQUEST_OFF'&&!naRoute('OFF',$allowed[$id])&&!in_array($normalized,$previousRequests,true))throw new DomainException('Request-Off is not available to higher fixed-schedule nursing positions.');
        if($type==='REQUEST_OFF'&&$status!=='PENDING'){$authorized=false;foreach($previous['requests']??[] as $old)if((int)$old['employee_id']===$id&&$old['date']===$date&&$old['type']===$type&&$old['status']===$status)$authorized=true;if(!$authorized)throw new DomainException('Request-Off decisions must use the assigned approver in Request-Off Management.');}
        if (!$access['approve'] && $status !== 'PENDING' && !in_array($normalized, $previousRequests, true)) throw new DomainException('Only an approver may approve or reject requests.');
        $requests[] = $normalized; $requestKeys[$key] = true;
    }
    // Draft membership may change; authoritative absence records for retained employees remain protected.
    if (!$access['approve']) foreach ($previousRequests as $request) {
        if (isset($seen[$request['employee_id']])&&$request['status'] === 'APPROVED' && !in_array($request, $requests, true)) throw new DomainException('An approver must change or remove an approved request.');
    }
    $rules = $input['rules'] ?? [];
    $coverage = [];
    foreach (nsScheduleDuties() as $duty => $hours) if ($hours) {
        $bounds = $rules['coverage'][$duty] ?? [];
        $min = nsScheduleInt($bounds['min'] ?? 0, 0, 200, 'minimum staffing');
        $max = nsScheduleInt($bounds['max'] ?? 200, $min, 200, 'maximum staffing');
        $coverage[$duty] = ['min' => $min, 'max' => $max];
    }
    $mode = $input['mode'] ?? '';
    $staffingTargets = [];
    $targets = $rules['staffingTargets'] ?? ['TRIAGE' => 1, 'NURSE' => 5, 'NA' => 1];
    if (!is_array($targets)) throw new InvalidArgumentException('Invalid staffing targets.');
    foreach (['TRIAGE', 'NURSE', 'NA'] as $role) $staffingTargets[$role] = nsScheduleInt($targets[$role] ?? -1, 0, 200, 'staffing target');
    if (array_sum($staffingTargets) > 200) throw new InvalidArgumentException('Total staffing per shift cannot exceed 200.');
    $staffingShifts = $rules['staffingShifts'] ?? [];
    if (!is_array($staffingShifts) || count($staffingShifts) > 6 || count(array_unique($staffingShifts)) !== count($staffingShifts)) throw new InvalidArgumentException('Invalid staffing shifts.');
    foreach ($staffingShifts as $shift) if (!is_string($shift) || !(nsScheduleDuties()[$shift] ?? 0)) throw new InvalidArgumentException('Invalid staffing shift.');
    if (!in_array($mode, ['AUTO', 'FIXED', 'MANUAL'], true)) throw new InvalidArgumentException('Invalid schedule mode.');
    // Retain approved exchange amendments even when the automatic generator rebuilds a draft.
    foreach ($previous['exchange_refs'] ?? [] as $id => $dates) foreach ($dates as $date => $reference) {
        if (($assignments[$id][$date] ?? null) !== ($previous['assignments'][$id][$date] ?? null)) throw new DomainException('Approved Exchange of Duty '.$reference.' cannot be overwritten by monthly generation or editing.');
    }
    $result = ['staff' => $staff, 'assignments' => $assignments, 'requests' => $requests, 'mode' => $mode,
        'rules' => ['minRest' => nsScheduleInt($rules['minRest'] ?? -1, 0, 48, 'minimum rest'),
            'maxConsecutive' => nsScheduleInt($rules['maxConsecutive'] ?? -1, 2, 6, 'maximum consecutive duties'),
            'minOff' => nsScheduleInt($rules['minOff'] ?? -1, 1, 2, 'minimum consecutive days off'),
            'maxOff' => nsScheduleInt($rules['maxOff'] ?? -1, 3, 31, 'maximum consecutive days off'),
            'offPer7' => nsScheduleInt($rules['offPer7'] ?? -1, 1, 6, 'mandatory OFF days'),
            'reviewed' => ($rules['reviewed'] ?? false) === true, 'staffingTargets' => $staffingTargets, 'staffingShifts' => $staffingShifts, 'coverage' => $coverage]];
    $result['locks']=$input['locks']??[];
    foreach($result['locks'] as $id=>$dates)foreach($dates as $date=>$locked)if(!isset($seen[$id])||!$validDate($date)||$locked!==true)throw new InvalidArgumentException('Invalid assignment lock.');
    $result['requestOffOverrides']=[];$overrides=$input['requestOffOverrides']??[];if(!is_array($overrides)||count($overrides)>200)throw new InvalidArgumentException('Invalid request-off accommodation reviews.');
    foreach($overrides as $id=>$dates){if(!isset($seen[$id])||!is_array($dates)||count($dates)>31)throw new InvalidArgumentException('Invalid request-off review employee.');foreach($dates as $date=>$review){if(!$validDate($date)||!is_array($review))throw new InvalidArgumentException('Invalid request-off review date.');$reason=trim($review['reason']??'');if(!$reason||strlen($reason)>1000)throw new InvalidArgumentException('Enter a request-off accommodation reason of up to 1,000 characters.');$approved=false;foreach($requests as $request)if((int)$request['employee_id']===(int)$id&&$request['date']===$date&&$request['type']==='REQUEST_OFF'&&$request['status']==='APPROVED')$approved=true;if(!$approved)continue;$old=$previous['requestOffOverrides'][$id][$date]??null;$result['requestOffOverrides'][$id][$date]=$old&&$old['reason']===$reason?$old:['reason'=>$reason,'reviewed_by'=>$access['user_id'],'reviewed_at'=>date('c')];}}
    if (!empty($previous['exchange_refs'])) $result['exchange_refs'] = $previous['exchange_refs'];
    if(isset($previous['history_reviewed_by']))$result['history_reviewed_by']=$previous['history_reviewed_by'];
    return $result;
}

function nsScheduleSummary(array $payload): array
{
    $summary = [];
    foreach ($payload['staff'] as $employee) {
        $counts = array_count_values($payload['assignments'][$employee['id']] ?? []);
        $hours = 0; $eight = 0; $twelve = 0;
        foreach (nsScheduleDuties() as $duty => $value) {
            $value=$payload['shiftMaster'][$duty]['hours']??$value;
            $count = $counts[$duty] ?? 0; $hours += $count * $value;
            if ($value === 8) $eight += $count;
            if ($value === 12) $twelve += $count;
        }
        $target=$payload['rules']['target']??NS_SCHEDULE_TARGET;
        $leaveCredits=0;foreach($payload['requests']??[] as $request)if((int)$request['employee_id']===(int)$employee['id']&&!empty($request['official_leave'])&&$request['status']==='APPROVED'&&($payload['shiftMaster'][$payload['assignments'][$employee['id']][$request['date']]??'']['hours']??0)==0)$leaveCredits+=$payload['leaveCredits'][$request['leave_code']??'']??0;
        $summary[] = ['employee_id' => $employee['id'], 'hours' => $hours, 'target' => $target,
            'leave_credit_hours'=>$leaveCredits,'actual_rendered_hours'=>null,
            'eight' => $eight, 'twelve' => $twelve, 'variance' => $hours - $target,
            'duty_target' => nsDutyTarget($employee,(float)$target), 'approved_overtime_hours'=>0,
            'status' => $hours === nsDutyTarget($employee,(float)$target) ? 'COMPLETE' : ($hours < nsDutyTarget($employee,(float)$target) ? 'DEFICIT' : 'EXCESS')];
    }
    return $summary;
}

function nsScheduleChanges(?array $old, array $next): array
{
    $changes = [];
    $ids = array_unique(array_merge(array_keys($old['assignments'] ?? []), array_keys($next['assignments'])));
    foreach ($ids as $id) {
        $before = $old['assignments'][$id] ?? []; $after = $next['assignments'][$id] ?? [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $date) {
            if (($before[$date] ?? null) !== ($after[$date] ?? null)) $changes[] = ['employee_id' => (int) $id, 'date' => $date, 'previous' => $before[$date] ?? null, 'next' => $after[$date] ?? null];
        }
    }
    return $changes;
}

function nsScheduleDepartmentRoster(array $payload, array $master): array
{
    $saved = array_column($payload['staff'], null, 'id');
    $pattern = $payload['staff'][0]['pattern'] ?? '8';
    $staff = [];
    foreach ($master as $employee) {
        $staff[] = array_replace($employee, $saved[$employee['id']] ?? ['pattern' => $pattern], ['active' => true]);
        unset($saved[$employee['id']]);
    }
    // Retain existing duties and identities after transfers or deactivation.
    foreach ($saved as $employee) { $employee['active'] = false; $staff[] = $employee; }
    $payload['staff'] = nsScheduleSortEmployees($staff);
    return $payload;
}

function nsScheduleRead(mysqli $conn, array $access, string $department, string $month, ?array $previewStaff=null): array
{
    nsScheduleScope($access, $department);
    $start = nsScheduleMonth($month);
    $row = nsScheduleQuery($conn, 'SELECT * FROM nursing_schedule_months WHERE department=? AND month_start=?', [$department, $start])->get_result()->fetch_assoc();
    $master = nsScheduleStaff($conn, $access, $department);
    $payload = $row ? nsScheduleDecode($access['edit'] ? $row['draft_json'] : $row['final_json']) : null;
    $historical=nsScheduleQuery($conn,'SELECT * FROM nursing_schedule_history WHERE department=? AND month_start=? ORDER BY version DESC LIMIT 1',[$department,$start])->get_result()->fetch_assoc();
    if(!$payload&&$historical&&$access['edit'])$payload=nsScheduleDecode($historical['payload_json']);
    $exists = $payload !== null;
    if (!$payload) $payload = nsScheduleEmpty([]);
    if ($access['edit'] && (!$historical || $row) && (!$row || in_array($row['status'], ['DRAFT', 'RETURNED FOR REVISION'], true))) {
        $payload = nsScheduleDepartmentRoster($payload, $master);
    }
    $currentStaff = array_column($master, null, 'id');
    foreach ($payload['staff'] as &$employee) {
        if (isset($currentStaff[$employee['id']])) {
            $employee['name'] = $currentStaff[$employee['id']]['name'];
            $employee['position'] = $currentStaff[$employee['id']]['position'];
            if(!$row||in_array($row['status'],['DRAFT','RETURNED FOR REVISION'],true)){$employee['department']=$currentStaff[$employee['id']]['department'];$employee['role']=$currentStaff[$employee['id']]['role'];}
            $employee['category'] = $currentStaff[$employee['id']]['category'];
            $employee['staffingRole'] = $employee['category'] === 'NA' ? 'NA' : (($employee['staffingRole'] ?? '') === 'TRIAGE' ? 'TRIAGE' : 'NURSE');
        }
    }
    unset($employee);
    if (!$access['edit']) {
        $payload['staff'] = array_values(array_filter($payload['staff'], fn($employee) => (int) $employee['id'] === $access['user_id']));
        $payload['assignments'] = isset($payload['assignments'][$access['user_id']]) ? [$access['user_id'] => $payload['assignments'][$access['user_id']]] : [];
        $payload['requests'] = array_values(array_filter($payload['requests'], fn($request) => (int) $request['employee_id'] === $access['user_id']));
    } else {
        $active = array_column($master, null, 'id');
        foreach ($payload['staff'] as &$employee) $employee['active'] = isset($active[$employee['id']]);
        unset($employee);
    }
    $boundaries = []; $ids = array_column($previewStaff??$payload['staff'], 'id');
    $date = new DateTimeImmutable($start);
    $neighbors = nsScheduleQuery($conn, 'SELECT department,month_start,draft_json,final_json FROM nursing_schedule_months WHERE month_start BETWEEN ? AND ? AND NOT(department=? AND month_start=?)',
        [$date->modify('-1 month')->format('Y-m-d'), $date->modify('+1 month')->format('Y-m-d'), $department, $start])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($neighbors as $neighbor) {
        // Only final assignments are authoritative across months and units.
        $document = nsScheduleDecode($neighbor['final_json']);
        if (!$document) continue;
        foreach ($document['assignments'] as $id => $days) if (in_array((int) $id, $ids, true)) {
            foreach ($days as $day => $duty) if ($day >= $date->modify('-14 days')->format('Y-m-d') && $day <= $date->modify('last day of this month')->modify('+14 days')->format('Y-m-d')) {
                $boundaries[] = ['employee_id' => (int) $id, 'date' => $day, 'duty' => $duty,'shift_definition'=>$document['shiftMaster'][$duty]??nsShiftDefaults()[$duty]??null];
            }
        }
    }
    $historyRows=nsScheduleQuery($conn,'SELECT h.* FROM nursing_schedule_history h WHERE h.month_start BETWEEN ? AND ? AND h.version=(SELECT MAX(x.version) FROM nursing_schedule_history x WHERE x.department=h.department AND x.month_start=h.month_start) AND NOT EXISTS(SELECT 1 FROM nursing_schedule_months m WHERE m.department=h.department AND m.month_start=h.month_start AND m.final_json IS NOT NULL)',[$date->modify('-1 month')->format('Y-m-d'),$date->modify('+1 month')->format('Y-m-d')])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($historyRows as $history){if($history['department']===$department&&$history['month_start']===$start)continue;$document=nsScheduleDecode($history['payload_json']);foreach($document['assignments'] as $id=>$days)if(in_array((int)$id,$ids,true))foreach($days as $day=>$duty)$boundaries[]=['employee_id'=>(int)$id,'date'=>$day,'duty'=>$duty,'historical'=>true,'shift_definition'=>$document['shiftMaster'][$duty]??nsShiftDefaults()[$duty]??null];}
    if(!$row||$row['status']!=='PUBLISHED'||empty($payload['shiftMaster']))$payload=nsPolicyHydrate($conn,$payload,$department,$month);
    if(function_exists('nrScheduleRequests')&&(!$row||$row['status']!=='PUBLISHED'))$payload=nrScheduleRequests($conn,$payload,$month);
    return ['historyVersion'=>(int)($historical['version']??0),'historical'=>!$row&&!!$historical,'exists' => $exists, 'status' => $exists&&$row ? ($access['edit'] ? $row['status'] : $row['final_status']) : 'DRAFT',
        'version' => $exists&&$row ? (int) ($access['edit'] ? $row['version'] : $row['final_version']) : 0,
        'submitted_by' => $access['edit'] && $row ? (int) $row['submitted_by'] : null,
        'payload' => $payload, 'master' => $access['edit'] ? $master : [], 'boundaries' => $boundaries,
        'summary' => nsScheduleSummary($payload)];
}

function nsScheduleSave(mysqli $conn, array $access, string $department, string $month, array $input): array
{
    nsScheduleRequireCurrentMonth($month);
    nsScheduleScope($access, $department);
    if (!$access['edit']) throw new DomainException('Your schedule is read-only.');
    $start = nsScheduleMonth($month);
    $action = (string) ($input['action'] ?? '');
    if (!in_array($action, ['save', 'submit', 'approve', 'return', 'publish', 'revise'], true)) throw new InvalidArgumentException('Invalid schedule action.');
    $remarks = trim((string) ($input['remarks'] ?? ''));
    if ($remarks === '' || strlen($remarks) > 1000) throw new InvalidArgumentException('Enter a revision reason of 1 to 1,000 characters.');
    $conn->begin_transaction();
    try {
        nsScheduleQuery($conn,'SELECT id FROM nursing_exchange_mutex WHERE id=1 FOR UPDATE')->get_result();
        $currentActor=nsScheduleQuery($conn,'SELECT user_id,role,department,status,is_active FROM users WHERE user_id=? FOR UPDATE',[$access['user_id']])->get_result()->fetch_assoc();
        if(!$currentActor||!$currentActor['is_active']||$currentActor['status']!=='ACTIVE')throw new DomainException('An active scheduler account is required.');
        $access=nsScheduleAccess($currentActor);nsScheduleScope($access,$department);if(!$access['edit'])throw new DomainException('Your current role cannot edit schedules.');
        $row = nsScheduleQuery($conn, 'SELECT * FROM nursing_schedule_months WHERE department=? AND month_start=? FOR UPDATE', [$department, $start])->get_result()->fetch_assoc();
        if ((int) ($input['version'] ?? -1) !== (int) ($row['version'] ?? 0)) throw new RuntimeException('This schedule changed in another session. Reload before saving.', 409);
        $status = $row['status'] ?? 'DRAFT';
        $previous = $row ? nsScheduleDecode($row['draft_json']) : null;
        $payload = $previous;
        if (in_array($action, ['save', 'submit'], true)) {
            if (!in_array($status, ['DRAFT', 'RETURNED FOR REVISION'], true)) throw new DomainException('Start a revision before editing a final schedule.');
            if (!is_array($input['payload'] ?? null)) throw new InvalidArgumentException('Missing schedule.');
            if (!is_array($input['payload']['staff'] ?? null)) throw new InvalidArgumentException('Invalid department roster.');
            $master = nsScheduleStaff($conn, $access, $department);
            $selected = array_column($input['payload']['staff'] ?? [], 'id');
            if (array_diff(array_column($master, 'id'), $selected)) throw new DomainException('All eligible department employees must be included. Reload the schedule to refresh the department roster.');
            if(function_exists('nrScheduleRequests'))$input['payload']=nrScheduleRequests($conn,$input['payload'],$month);
            $validationPrevious=$previous;
            if(function_exists('nrScheduleRequests')){$live=nrScheduleRequests($conn,['staff'=>$input['payload']['staff']??[],'requests'=>$previous['requests']??[]],$month);$validationPrevious=$previous??['staff'=>[]];$validationPrevious['requests']=$live['requests'];}
            $payload = nsScheduleValidate($input['payload'], $master, $validationPrevious, $access, $month);
            if ($action === 'submit' && (!$payload['staff'] || !$payload['rules']['reviewed'])) throw new InvalidArgumentException('Load staff and review the staffing and rest rules before submitting.');
            $status = $action === 'submit' ? 'FOR APPROVAL' : $status;
        } elseif ($action === 'revise') {
            if (!in_array($status, ['APPROVED', 'PUBLISHED'], true)) throw new DomainException('Only a final schedule can start a new revision.');
            $status = 'DRAFT';
        } else {
            if (!$row || !$access['approve']) throw new DomainException('An approver is required.');
            if (in_array($action, ['approve', 'return'], true) && $status !== 'FOR APPROVAL') throw new DomainException('This schedule is not awaiting approval.');
            if ($action === 'approve' && (int) $row['submitted_by'] === $access['user_id']) throw new DomainException('Another authorized approver must review your submitted schedule.');
            if ($action === 'approve' && ($input['reviewAcknowledged'] ?? false) !== true) throw new InvalidArgumentException('Confirm that hours, coverage, and conflicts have been reviewed.');
            if ($action === 'publish' && $status !== 'APPROVED') throw new DomainException('Only approved schedules can be published.');
            $status = ['approve' => 'APPROVED', 'return' => 'RETURNED FOR REVISION', 'publish' => 'PUBLISHED'][$action];
        }
        if(in_array($action,['approve','publish'],true))$payload=nsScheduleValidate($payload,nsScheduleStaff($conn,$access,$department),$previous,$access,$month);
        $payload=nsPolicyHydrate($conn,$payload,$department,$month);
        if(function_exists('nrScheduleRequests'))$payload=nrScheduleRequests($conn,$payload,$month);
        if(in_array($action,['submit','approve','publish'],true)){
            $context=nsScheduleRead($conn,$access,$department,$month,$payload['staff']);
            $payload['missing_history']=[];foreach($payload['staff'] as $employee){$previousDate=(new DateTimeImmutable($month.'-01'))->modify('-1 day')->format('Y-m-d');$known=false;foreach($context['boundaries'] as $boundary)if((int)$boundary['employee_id']===(int)$employee['id']&&$boundary['date']===$previousDate)$known=true;if(!$known)$payload['missing_history'][]=(int)$employee['id'];}
            $errors=nsPolicyErrors($payload,$month,$context['boundaries']);
            if($errors)throw new DomainException('Resolve mandatory schedule checks: '.implode('; ',array_slice($errors,0,10)));
            if(!empty($input['historyReviewed']))$payload['history_reviewed_by']=$access['user_id'];
            if($payload['missing_history']&&empty($payload['history_reviewed_by']))throw new DomainException('Review and acknowledge missing month-boundary history before finalizing.');
        }
        $version = (int) ($row['version'] ?? 0) + 1;
        $json = nsScheduleEncode($payload);
        $final = in_array($action, ['approve', 'publish'], true) ? $json : ($row['final_json'] ?? null);
        $finalStatus = in_array($action, ['approve', 'publish'], true) ? $status : ($row['final_status'] ?? null);
        $finalVersion = in_array($action, ['approve', 'publish'], true) ? $version : ($row['final_version'] ?? null);
        $submitted = $action === 'submit' ? $access['user_id'] : ($row['submitted_by'] ?? null);
        if ($row) {
            nsScheduleQuery($conn, 'UPDATE nursing_schedule_months SET status=?,version=?,draft_json=?,final_json=?,final_status=?,final_version=?,updated_by=?,submitted_by=? WHERE id=?',
                [$status, $version, $json, $final, $finalStatus, $finalVersion, $access['user_id'], $submitted, $row['id']]);
            $id = (int) $row['id'];
        } else {
            nsScheduleQuery($conn, 'INSERT INTO nursing_schedule_months(department,month_start,status,version,draft_json,updated_by,submitted_by) VALUES(?,?,?,?,?,?,?)', [$department, $start, $status, $version, $json, $access['user_id'], $submitted]);
            $id = $conn->insert_id;
        }
        $actor = nsScheduleQuery($conn, 'SELECT first_name,last_name FROM users WHERE user_id=?', [$access['user_id']])->get_result()->fetch_assoc();
        nsScheduleQuery($conn, 'INSERT INTO nursing_schedule_revisions(schedule_id,version,action,changed_by,changed_by_name,remarks,payload_json,changes_json) VALUES(?,?,?,?,?,?,?,?)',
            [$id, $version, $action, $access['user_id'], trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? '')), $remarks, $json, nsScheduleEncode(nsScheduleChanges($previous, $payload))]);
        if($action==='publish'&&function_exists('nrPublishOutcomes')){$notificationPayload=$payload;$notificationPayload['published_by']=$access['user_id'];nrPublishOutcomes($conn,$notificationPayload,$month,$department,$version);}
        if($action==='publish'&&function_exists('nrSchedulePublishedNotice'))nrSchedulePublishedNotice($conn,$payload,$department,$month,$version);
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        if ($error instanceof mysqli_sql_exception && in_array($error->getCode(), [1062, 1213], true)) throw new RuntimeException('Another session saved this schedule. Reload before saving.', 409);
        throw $error;
    }
    return nsScheduleRead($conn, $access, $department, $month);
}
