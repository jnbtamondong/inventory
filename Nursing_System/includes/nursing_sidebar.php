<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nursing_reports.php';
require_once __DIR__ . '/duty_exchange_navigation.php';
require_once __DIR__ . '/patient-care-navigation.php';

$currentPage = basename($_SERVER['PHP_SELF']);

function nursingSidebarDepartment(): string {
    $module = strtolower(trim((string) ($_GET['module'] ?? '')));
    $moduleDepartments = [
        'er' => 'ER',
        'triage' => 'ER',
        'opd' => 'OPD',
        'admission' => 'ADMITTING',
        'admitting' => 'ADMITTING',
        'ob_pedia_ward' => 'WARD_2F',
        'ward' => 'WARD_3F',
        'nicu' => 'NICU',
        'micu' => 'ICU',
        'icu' => 'ICU',
        'ldr' => 'LR_DR',
        'or' => 'OR',
    ];

    $pageDepartments = [
        'admitted_patients.php' => 'ADMITTING',
        'admitted_list.php' => 'ADMITTING',
        'movement_history.php' => 'ADMITTING',
    ];

    return $moduleDepartments[$module]
        ?? $pageDepartments[basename($_SERVER['PHP_SELF'] ?? '')]
        ?? canonicalDepartment($_SESSION['department'] ?? '');
}

function nursingSidebarPageExists(string $href): bool {
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
    return $href === '#' || is_file(__DIR__ . '/../pages/' . $path);
}

function nursingSidebarCan(array $item): bool {
    if (($item['label'] ?? '') === 'EXCHANGE OF DUTY' && !exNavigationLinks($_SESSION ?? [])) return false;
    if (basename(parse_url($item['href'] ?? '', PHP_URL_PATH) ?? '') === 'duty_exchange.php') {
        parse_str(parse_url($item['href'], PHP_URL_QUERY) ?? '', $exchangeQuery);
        if (!in_array($exchangeQuery['view'] ?? 'dashboard', array_column(exNavigationLinks($_SESSION ?? []), 1), true)) return false;
    }
    if (!empty($item['exclude_roles']) && hasRole($item['exclude_roles'])) {
        return false;
    }
    if (!empty($item['exclude_departments'])) {
        $excludedDepartments = array_map('canonicalDepartment', (array) $item['exclude_departments']);
        if (in_array(nursingSidebarDepartment(), $excludedDepartments, true)) {
            return false;
        }
    }
    if (!empty($item['departments'])) {
        $allowedDepartments = array_map('canonicalDepartment', (array) $item['departments']);
        // Visibility follows the authenticated user's department access, not the
        // currently selected page/module. Leadership can view permitted units.
        if (!isDepartmentAllowed($allowedDepartments)) {
            return false;
        }
    }
    if (!empty($item['roles']) && !isSuperAdmin() && !hasRole($item['roles'])) {
        return false;
    }
    if (!empty($item['permission'])) {
        [$module, $action, $department] = array_pad($item['permission'], 3, null);
        if (!hasPermission($module, $action ?: 'view', $department)) {
            return false;
        }
    }
    if (!empty($item['report_key']) && !nursingReportCanAccess($item['report_key'])) {
        return false;
    }
    return nursingSidebarPageExists($item['href'] ?? '#');
}

function nursingSidebarActive(array $item, string $currentPage): bool {
    $href = $item['href'] ?? '#';
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
    if (basename($path) !== $currentPage) {
        return false;
    }
    $query = [];
    parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
    foreach (['module', 'area', 'status', 'destination', 'department', 'view', 'kind'] as $key) {
        $currentValue=$_GET[$key]??'';
        if($key==='module'&&!isset($_GET['module'])){
            $clinical=function_exists('clinicalModuleByPage')?clinicalModuleByPage($currentPage):null;
            $currentValue=$clinical['key']??(str_starts_with($currentPage,'opd_')?'opd':
                (str_starts_with($currentPage,'ward_')?(canonicalDepartment($_SESSION['department']??'')==='WARD_2F'?'ob_pedia_ward':'ward'):
                (in_array($currentPage,['patient_registration.php','emergency_room_dashboard.php','er_beds_view.php','er_statistics_dashboard.php'],true)?'er':($currentPage==='triage.php'?'triage':''))));
        }
        if (isset($query[$key]) && (string)$currentValue !== (string) $query[$key]) {
            return false;
        }
    }
    if (!empty($item['unavailable'])) return false;
    return true;
}

function nursingSidebarFilter(array $items): array {
    return array_values(array_filter($items, 'nursingSidebarCan'));
}

$menu = [
    [
        'label' => 'Dashboard',
        'icon' => 'fa-table-cells-large',
        'href' => 'dashboard.php',
        'permission' => ['nursing', 'view'],
    ],
    [
        'label'=>'Nursing Schedule Management','icon'=>'fa-calendar-days','href'=>'#',
        'roles'=>['SUPER_ADMIN','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE','HEAD_NURSE','STAFF_NURSE','NURSING_ATTENDANT'],
        'items'=>[
            ['label'=>'Monthly Duty Scheduling','icon'=>'fa-calendar-days','href'=>'schedule_management.php','roles'=>['SUPER_ADMIN','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE','HEAD_NURSE']],
            ['label'=>'Request Leave / Off','icon'=>'fa-calendar-xmark','href'=>'nursing_requests.php?kind=LEAVE&view=new'],
            ['label'=>'EXCHANGE OF DUTY','icon'=>'fa-right-left','href'=>'duty_exchange.php?view=dashboard'],
        ],
    ],
    [
        'label' => 'Emergency Room',
        'icon' => 'fa-truck-medical',
        'href' => '#',
        'departments' => ['ER'],
        'permission' => ['nursing', 'view', 'ER'],
        'items' => [
            ['label' => 'ER Dashboard', 'icon' => 'fa-chart-line', 'href' => 'emergency_room_dashboard.php?module=er', 'permission' => ['nursing', 'view', 'ER']],
            ['label' => 'ER Patients', 'icon' => 'fa-users', 'href' => 'patients.php?module=er', 'permission' => ['patients','view','ER']],
            ['label' => 'Patient Registration', 'icon' => 'fa-user-plus', 'href' => 'patient_registration.php?module=er', 'permission' => ['patients', 'create', 'ER']],
            ['label' => 'Triage / ER Record', 'icon' => 'fa-clipboard-list', 'href' => 'triage.php?module=triage', 'permission' => ['patients', 'edit', 'ER']],
            ['label' => 'Patient Queue', 'icon' => 'fa-list-ul', 'href' => 'patient_queue.php?module=triage', 'permission' => ['patients', 'view']],
            ['label' => 'ER Bed Tracker', 'icon' => 'fa-truck-medical', 'href' => 'er_beds_view.php?module=er', 'permission' => ['beds', 'view', 'ER']],
            ['label' => 'ER Disposition', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'discharge_patients.php?area=emergency_room&module=er', 'roles'=>['SUPER_ADMIN'], 'permission' => ['patients', 'edit']],
            ['label' => 'Discharge Tracking', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'patient_live_tracker.php?department=ER&status=discharge', 'permission' => ['patients','view','ER']],
            ['label' => 'ER Reports', 'icon' => 'fa-file-medical', 'href' => 'nursing_er_statistics_report.php', 'permission' => ['reports', 'view', 'ER'], 'report_key' => 'er_statistics'],
        ],
    ],
    [
        'label' => 'Outpatient Department',
        'icon' => 'fa-user-doctor',
        'href' => '#',
        'departments' => ['OPD'],
        'permission' => ['opd', 'view', 'OPD'],
        'items' => [
            ['label' => 'OPD Dashboard', 'icon' => 'fa-chart-line', 'href' => 'opd_dashboard.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'New OPD Consultation', 'icon' => 'fa-notes-medical', 'href' => 'opd_consultation.php?module=opd', 'permission' => ['opd', 'create', 'OPD']],
            ['label' => 'OPD Patients', 'icon' => 'fa-users', 'href' => 'opd_patients.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'For ER', 'icon' => 'fa-truck-medical', 'href' => 'opd_for_er.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'For Admission', 'icon' => 'fa-hospital-user', 'href' => 'opd_for_admission.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'For OR', 'icon' => 'fa-suitcase-medical', 'href' => 'opd_for_or.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'Follow-Up', 'icon' => 'fa-calendar-days', 'href' => 'opd_follow_up.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
            ['label' => 'OPD Reports', 'icon' => 'fa-file-medical', 'href' => 'opd_reports.php?module=opd', 'permission' => ['opd', 'view', 'OPD']],
        ],
    ],
    [
        'label' => 'Doctor Module',
        'icon' => 'fa-user-doctor',
        'href' => '#',
        'roles' => doctorRoles(),
        'items' => [
            ['label' => 'Consultation Queue', 'icon' => 'fa-stethoscope', 'href' => 'consultation_queue.php', 'roles' => doctorRoles()],
            ['label' => 'Patient History', 'icon' => 'fa-clock-rotate-left', 'href' => 'patient_history.php', 'roles' => doctorRoles()],
            ['label' => 'Ward Patients', 'icon' => 'fa-bed-pulse', 'href' => 'ward_admitted_patients.php?module=ward', 'roles' => doctorRoles()],
        ],
    ],
    [
        'label' => 'Admitting Module',
        'icon' => 'fa-hospital-user',
        'href' => '#',
        'departments' => ['ADMITTING'],
        'roles' => admissionRoles(),
        'items' => [
            ['label' => 'Admitting Form', 'icon' => 'fa-file-medical', 'href' => 'admitted_patients.php?classification=emergency_room&module=admission', 'roles' => admissionRoles()],
            ['label' => 'Admission List', 'icon' => 'fa-list-check', 'href' => 'admitted_list.php?module=admission', 'roles' => admissionRoles()],
            ['label' => 'ER Bed Assignment', 'icon' => 'fa-bed-pulse', 'href' => 'er_beds_view.php?module=admission', 'roles' => admissionRoles()],
            ['label' => 'Ward Bed Assignment', 'icon' => 'fa-bed', 'href' => 'ward_beds_view.php?module=admission', 'roles' => admissionRoles()],
            ['label' => 'Movement History', 'icon' => 'fa-route', 'href' => 'movement_history.php?module=admission', 'roles' => admissionRoles()],
        ],
    ],
    [
        'label' => 'OB/Pedia Ward',
        'icon' => 'fa-hospital',
        'href' => '#',
        'departments' => ['WARD_2F'],
        'permission' => ['nursing', 'view', 'WARD_2F'],
        'items' => [
            ['label' => 'OB/Pedia Dashboard', 'icon' => 'fa-chart-line', 'href' => 'ward_dashboard.php?module=ob_pedia_ward', 'permission' => ['nursing', 'view', 'WARD_2F']],
            ['label' => 'OB/Pedia Bed Tracker', 'icon' => 'fa-bed-pulse', 'href' => 'ward_beds_view.php?module=ob_pedia_ward', 'permission' => ['beds', 'view', 'WARD_2F']],
            ['label' => 'OB/Pedia Census', 'icon' => 'fa-chart-column', 'href' => 'ward_statistics_dashboard.php?module=ob_pedia_ward', 'permission' => ['nursing', 'view', 'WARD_2F']],
            ['label' => 'Patient Monitoring', 'icon' => 'fa-heart-pulse', 'href' => 'ward_admitted_patients.php?module=ob_pedia_ward', 'permission' => ['patients', 'view', 'WARD_2F']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=WARD&module=ob_pedia_ward', 'permission' => ['patient_movements', 'edit']],
            ['label' => 'Nursing Notes', 'icon' => 'fa-notes-medical', 'href' => 'nursing_notes.php?module=ob_pedia_ward', 'permission' => ['patients', 'view']],
            ['label' => 'Discharge', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'discharge_patients.php?area=ward&module=ob_pedia_ward', 'permission' => ['patients', 'edit']],
            ['label' => 'OB/Pedia Reports', 'icon' => 'fa-file-medical', 'href' => 'nursing_patient_census_summary.php?module=ob_pedia_ward', 'permission' => ['reports', 'view'], 'report_key' => 'patient_census'],
        ],
    ],
    [
        'label' => 'Medical/Surgery Ward',
        'icon' => 'fa-hospital',
        'href' => '#',
        'departments' => ['WARD_3F'],
        'permission' => ['nursing', 'view', 'WARD_3F'],
        'items' => [
            ['label' => 'Medical/Surgery Dashboard', 'icon' => 'fa-chart-line', 'href' => 'ward_dashboard.php?module=ward', 'permission' => ['nursing', 'view', 'WARD_3F']],
            ['label' => 'Medical/Surgery Bed Tracker', 'icon' => 'fa-bed-pulse', 'href' => 'ward_beds_view.php?module=ward', 'permission' => ['beds', 'view', 'WARD_3F']],
            ['label' => 'Medical/Surgery Census', 'icon' => 'fa-chart-column', 'href' => 'ward_statistics_dashboard.php?module=ward', 'permission' => ['nursing', 'view', 'WARD_3F']],
            ['label' => 'Patient Monitoring', 'icon' => 'fa-heart-pulse', 'href' => 'ward_admitted_patients.php?module=ward', 'permission' => ['patients', 'view', 'WARD_3F']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=WARD&module=ward', 'permission' => ['patient_movements', 'edit']],
            ['label' => 'Nursing Notes', 'icon' => 'fa-notes-medical', 'href' => 'nursing_notes.php?module=ward', 'permission' => ['patients', 'view']],
            ['label' => 'Discharge', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'discharge_patients.php?area=ward&module=ward', 'permission' => ['patients', 'edit']],
            ['label' => 'Medical/Surgery Reports', 'icon' => 'fa-file-medical', 'href' => 'nursing_patient_census_summary.php?module=ward', 'permission' => ['reports', 'view'], 'report_key' => 'patient_census'],
        ],
    ],
    [
        'label' => 'NICU',
        'icon' => 'fa-baby',
        'href' => '#',
        'departments' => ['NICU'],
        'permission' => ['nicu', 'view', 'NICU'],
        'items' => [
            ['label' => 'NICU Dashboard', 'icon' => 'fa-chart-line', 'href' => 'nicu_dashboard.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'NICU Patients', 'icon' => 'fa-baby', 'href' => 'nicu_patients.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'Inborn Patients', 'icon' => 'fa-baby-carriage', 'href' => 'nicu_inborn.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'Outborn Patients', 'icon' => 'fa-child', 'href' => 'nicu_outborn.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'NICU Bed Tracker', 'icon' => 'fa-bed-pulse', 'href' => 'nicu_bed_tracker.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=NICU', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'NICU Discharge', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'nicu_discharge.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
            ['label' => 'NICU Reports', 'icon' => 'fa-file-medical', 'href' => 'nicu_reports.php?module=nicu', 'permission' => ['nicu', 'view', 'NICU']],
        ],
    ],
    [
        'label' => 'MICU',
        'icon' => 'fa-heart-pulse',
        'href' => '#',
        'departments' => ['ICU'],
        'permission' => ['micu', 'view', 'ICU'],
        'items' => [
            ['label' => 'MICU Dashboard', 'icon' => 'fa-chart-line', 'href' => 'micu_dashboard.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'MICU Patients', 'icon' => 'fa-users', 'href' => 'micu_patients.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'MICU Bed Tracker', 'icon' => 'fa-bed-pulse', 'href' => 'micu_bed_tracker.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'MICU Monitoring', 'icon' => 'fa-heart-circle-check', 'href' => 'micu_monitoring.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=ICU', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'MICU Discharge', 'icon' => 'fa-person-walking-arrow-right', 'href' => 'micu_discharge.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
            ['label' => 'MICU Reports', 'icon' => 'fa-file-medical', 'href' => 'micu_reports.php?module=micu', 'permission' => ['micu', 'view', 'ICU']],
        ],
    ],
    [
        'label' => 'Labor Room / Delivery Room',
        'icon' => 'fa-person-pregnant',
        'href' => '#',
        'departments' => ['LR_DR'],
        'permission' => ['ldr', 'view', 'LR_DR'],
        'items' => [
            ['label' => 'LR / DR Dashboard', 'icon' => 'fa-chart-line', 'href' => 'ldr_dashboard.php?module=ldr', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Maternal Patient Record', 'icon' => 'fa-user', 'href' => 'ldr_patients.php?module=ldr', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Labor Monitoring', 'icon' => 'fa-heart-pulse', 'href' => 'ldr_labor_monitoring.php?module=ldr', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Delivery Record', 'icon' => 'fa-baby', 'href' => 'ldr_delivery_record.php?module=ldr', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=LR_DR', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Newborn Record', 'icon' => 'fa-baby-carriage', 'href' => 'newborn_record.php', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'Postpartum Monitoring', 'icon' => 'fa-clipboard-check', 'href' => 'postpartum_monitoring.php', 'permission' => ['ldr', 'view', 'LR_DR']],
            ['label' => 'LR / DR Reports', 'icon' => 'fa-file-medical', 'href' => 'ldr_reports.php?module=ldr', 'permission' => ['ldr', 'view', 'LR_DR']],
        ],
    ],
    [
        'label' => 'Operating Room',
        'icon' => 'fa-suitcase-medical',
        'href' => '#',
        'departments' => ['OR'],
        'permission' => ['operating_room', 'view', 'OR'],
        'items' => [
            ['label' => 'OR Dashboard', 'icon' => 'fa-chart-line', 'href' => 'or_dashboard.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'OR Schedule', 'icon' => 'fa-calendar-check', 'href' => 'or_schedule.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'Pre-op Checklist', 'icon' => 'fa-clipboard-check', 'href' => 'or_preop_checklist.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'OR Case Record', 'icon' => 'fa-file-medical', 'href' => 'or_for_patients.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'Intra-op Record', 'icon' => 'fa-notes-medical', 'href' => 'or_intraop_record.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'Post-op / Recovery', 'icon' => 'fa-bed-pulse', 'href' => 'or_recovery.php?module=or', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'Trans In / Trans Out Patient', 'icon' => 'fa-route', 'href' => 'trans_out_patient.php?department=OR', 'permission' => ['operating_room', 'view', 'OR']],
            ['label' => 'OR Reports', 'icon' => 'fa-file-medical', 'href' => 'nursing_or_safe_operation_report.php', 'permission' => ['reports', 'view', 'OR'], 'report_key' => 'or_safe'],
        ],
    ],
    [
        'label' => 'Data Backup',
        'icon' => 'fa-database',
        'href' => 'data_backup.php',
        'roles' => ['SUPER_ADMIN'],
        'permission' => ['user_management', 'view'],
    ],
];

$visibleMenu = [];
$departmentMenus = [];
$departmentLabels = ['Emergency Room','Outpatient Department','OB/Pedia Ward','Medical/Surgery Ward','NICU','MICU','Labor Room / Delivery Room','Operating Room'];
foreach ($menu as $item) {
    if (!in_array($item['label'],$departmentLabels,true)) continue;
    $item['items'] = array_merge($item['items'] ?? [], nursingDepartmentCareLinks($item['label']));
    foreach ($item['items'] as &$careItem) {
        if (in_array(basename(parse_url($careItem['href'] ?? '', PHP_URL_PATH) ?? ''), ['nursing_notes.php','newborn_record.php','postpartum_monitoring.php'], true)) {
            $careItem['unavailable'] = true;
        }
    }
    unset($careItem);
    if (!empty($item['departments']) && !empty($item['items'])) {
        foreach ($item['items'] as &$childItem) {
            $childItem['departments'] = $childItem['departments'] ?? $item['departments'];
        }
        unset($childItem);
    }
    $item['items'] = !empty($item['items']) ? nursingSidebarFilter($item['items']) : [];
    $seenHrefs=[];
    $item['items']=array_values(array_filter($item['items'],static function($child)use(&$seenHrefs){
        $href=$child['href'];if(isset($seenHrefs[$href]))return false;$seenHrefs[$href]=true;return true;
    }));
    if (nursingSidebarCan($item) && $item['items']) $departmentMenus[] = $item;
}
$centralReports=count($departmentMenus)>1 && hasRole(['SUPER_ADMIN','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE']) && hasPermission('reports','view');
if ($centralReports) foreach($departmentMenus as &$departmentMenu) {
    $departmentMenu['items']=array_values(array_filter($departmentMenu['items'],static fn($child)=>!isset($child['report_key'])&&!str_contains(strtolower($child['label']),'reports')));
}unset($departmentMenu);
if($departmentMenus){
    $visibleMenu[]=['label'=>count($departmentMenus)>1?'Clinical Departments':'Assigned Department','heading'=>true];
    array_push($visibleMenu,...$departmentMenus);
}
$erAssigned=canonicalDepartment($_SESSION['department']??'')==='ER';
$monitoring=nursingSidebarFilter([
    ['label'=>$erAssigned?'ER Patient Tracker':'Patient Tracker','icon'=>'fa-route','href'=>$erAssigned?'er_patient_tracker.php':'patient_live_tracker.php','permission'=>['patients','view']],
    ['label'=>'Bed Tracker','icon'=>'fa-bed-pulse','href'=>'bed_tracker.php','permission'=>['beds','view']],
]);
if(!$erAssigned&&hasPermission('patients','view','ER'))$monitoring[]=['label'=>'ER Patient Tracker','icon'=>'fa-heart-pulse','href'=>'er_patient_tracker.php'];
if($monitoring){$visibleMenu[]=['label'=>'Monitoring','heading'=>true];array_push($visibleMenu,...$monitoring);}
if($centralReports)$visibleMenu[]=['label'=>'Reports','icon'=>'fa-file-medical','href'=>'reports.php?module=reports','permission'=>['reports','view']];
$visibleMenu[]=['label'=>'System','heading'=>true];
$visibleMenu[]=['label'=>'Settings','icon'=>'fa-gear','href'=>'account_settings.php'];
$visibleMenu[]=['label'=>'Back to Main Menu','icon'=>'fa-house','href'=>'system_menu.php'];
?>

<aside class="sidebar nursing-sidebar">
    <div class="sidebar-brand">
        <img class="nursing-sidebar-logo" src="../public/images/branding/jvgh_seal.png" alt="JVGH seal">
        <div class="nursing-sidebar-brand-copy"><strong>JVGH Nursing</strong><small>Nursing Services</small></div>
        <button class="sidebar-toggle" type="button" title="Open / close sidebar" aria-label="Open or close sidebar">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
    </div>
    <div class="nursing-sidebar-status"><span></span><div><strong><?php echo h(count($departmentMenus)===1?$departmentMenus[0]['label']:'Nursing Information System'); ?></strong><small>Clinical workspace</small></div></div>
    <ul class="menu">
        <?php foreach ($visibleMenu as $item): ?>
            <?php if(!empty($item['heading'])): ?><li class="nav-section"><?=h($item['label'])?></li><?php continue;endif; ?>
            <?php
                $hasChildren = !empty($item['items']);
                $isActive = nursingSidebarActive($item, $currentPage);
                if ($hasChildren) {
                    foreach ($item['items'] as $child) {
                        $isActive = $isActive || nursingSidebarActive($child, $currentPage);
                    }
                }
            ?>
            <li class="<?php echo $hasChildren ? 'has-submenu ' : ''; ?><?php echo $isActive ? 'open' : ''; ?>">
                <a href="<?php echo h($hasChildren ? '#' : $item['href']); ?>" class="<?php echo $isActive ? 'active' : ''; ?>" title="<?php echo h($item['label']); ?>">
                    <i class="fa-solid <?php echo h($item['icon']); ?>"></i>
                    <span><?php echo h($item['label']); ?></span>
                </a>
                <?php if ($hasChildren): ?>
                    <ul class="submenu">
                        <?php foreach ($item['items'] as $child): ?>
                            <?php $childActive = nursingSidebarActive($child, $currentPage); ?>
                            <li>
                                <?php if (!empty($child['unavailable'])): ?>
                                <span class="sidebar-unavailable" aria-disabled="true" title="This documentation workflow is not available yet."><i class="fa-solid <?php echo h($child['icon']); ?>" aria-hidden="true"></i><span><?php echo h($child['label']); ?><small>Not available yet</small></span></span>
                                <?php else: ?>
                                <a href="<?php echo h($child['href']); ?>" class="<?php echo $childActive ? 'active' : ''; ?>" title="<?php echo h($child['label']); ?>">
                                    <i class="fa-solid <?php echo h($child['icon']); ?>"></i>
                                    <span><?php echo h($child['label']); ?></span>
                                </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
