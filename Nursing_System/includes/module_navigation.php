<?php

require_once __DIR__ . '/clinical_modules.php';

function moduleNavDefinitions(): array {
    return [
        'dashboard' => [
            'label' => 'Nursing Dashboard',
            'title' => 'Nursing Dashboard',
            'href' => 'dashboard.php',
            'icon' => 'fa-table-cells-large',
            'accent' => 'blue',
            'permission' => ['nursing', 'view'],
            'items' => [
                ['Dashboard', 'dashboard.php', 'fa-chart-line'],
                ['Overview', 'dashboard.php#overview', 'fa-table-cells-large'],
                ['My Tasks', 'dashboard.php#tasks', 'fa-list-check'],
                ['Alerts', 'dashboard.php#alerts', 'fa-bell'],
            ],
        ],
        'live_tracker' => [
            'label' => 'Patient Live Tracker',
            'title' => 'Patient Live Tracker',
            'href' => 'patient_queue.php?module=live_tracker',
            'icon' => 'fa-route',
            'accent' => 'green',
            'permission' => ['patients', 'view'],
            'items' => [
                ['Live Tracker Dashboard', 'patient_queue.php?module=live_tracker', 'fa-heart-pulse'],
                ['Patient Movement Tracker', 'movement_history.php?module=live_tracker', 'fa-route'],
                ['Transfer Requests', 'movement_history.php?module=live_tracker&status=PENDING', 'fa-right-left'],
                ['Patients in Transit', 'movement_history.php?module=live_tracker&status=IN%20TRANSIT', 'fa-truck-medical'],
                ['Movement History', 'movement_history.php?module=live_tracker', 'fa-clock-rotate-left'],
            ],
        ],
        'triage' => [
            'label' => 'Add Patient / Triage',
            'title' => 'Add Patient / Triage',
            'href' => 'patients.php?action=choose&module=triage',
            'icon' => 'fa-user-plus',
            'accent' => 'teal',
            'permission' => ['patients', 'create'],
            'items' => [
                ['Add Patient', 'patients.php?action=choose&module=triage', 'fa-user-plus'],
                ['Triage Form', 'patients.php?action=choose&module=triage', 'fa-clipboard-list'],
                ['Triage List', 'patients.php?module=triage', 'fa-list-ul'],
                ['Waiting Patients', 'patient_queue.php?module=triage', 'fa-clock'],
                ['Triage Reports', 'reports.php?module=triage&area=emergency_room', 'fa-file-medical'],
            ],
        ],
        'er' => [
            'label' => 'Emergency Room',
            'title' => 'Emergency Room',
            'href' => 'emergency_room_dashboard.php?module=er',
            'icon' => 'fa-truck-medical',
            'accent' => 'red',
            'permission' => ['nursing', 'view', 'ER'],
            'items' => [
                ['ER Dashboard', 'emergency_room_dashboard.php?module=er', 'fa-chart-line'],
                ['ER Patients', 'patients.php?module=er', 'fa-users'],
                ['ER Assessment', 'patients.php?action=choose&module=er', 'fa-stethoscope'],
                ['For Admission', 'admitted_patients.php?module=er', 'fa-hospital-user'],
                ['ER Bed Tracker', 'er_beds_view.php?module=er', 'fa-bed-pulse'],
                ['Transfer to Another Department', 'movement_history.php?module=er&department=ER', 'fa-right-left'],
                ['Schedule for OR', 'or_schedule.php?module=er', 'fa-calendar-check'],
                ['Discharged Patients', 'discharge_patients.php?area=emergency_room&module=er', 'fa-person-walking-arrow-right'],
                ['ER Reports', 'reports.php?area=emergency_room&module=er', 'fa-file-medical'],
            ],
        ],
        'admission' => [
            'label' => 'Admission Queue',
            'title' => 'Admission Queue',
            'href' => 'admitted_patients.php?module=admission',
            'icon' => 'fa-clipboard-list',
            'accent' => 'orange',
            'permission' => ['patients', 'view'],
            'items' => [
                ['For Admission', 'admitted_patients.php?module=admission', 'fa-user-plus'],
                ['Pending Admission', 'admitted_patients.php?module=admission', 'fa-hourglass-half'],
                ['Accepted Admission', 'admitted_list.php?module=admission', 'fa-circle-check'],
                ['Admission History', 'patient_history.php?module=admission', 'fa-clock-rotate-left'],
            ],
        ],
        'opd' => [
            'label' => 'OPD / Out Patient Department',
            'title' => 'OPD / Out Patient Department',
            'href' => 'opd_dashboard.php?module=opd',
            'icon' => 'fa-stethoscope',
            'accent' => 'green',
            'permission' => ['opd', 'view', 'OPD'],
            'items' => [
                ['OPD Dashboard', 'opd_dashboard.php?module=opd', 'fa-chart-line'],
                ['OPD Patients', 'opd_patients.php?module=opd', 'fa-users'],
                ['OPD Consultation', 'opd_consultation.php?module=opd', 'fa-notes-medical'],
                ['Transfer to Another Department', 'movement_history.php?module=opd&department=OPD', 'fa-right-left'],
                ['For ER Referral', 'opd_for_er.php?module=opd', 'fa-truck-medical'],
                ['For Admission', 'opd_for_admission.php?module=opd', 'fa-hospital-user'],
                ['For OR Schedule', 'opd_for_or.php?module=opd', 'fa-calendar-check'],
                ['Follow-Up Patients', 'opd_follow_up.php?module=opd', 'fa-calendar-days'],
                ['OPD Reports', 'opd_reports.php?module=opd', 'fa-file-medical'],
            ],
        ],
        'ldr' => [
            'label' => 'Delivery Room / LR-DR',
            'title' => 'Delivery Room / LR-DR',
            'href' => 'ldr_dashboard.php?module=ldr',
            'icon' => 'fa-baby',
            'accent' => 'teal',
            'permission' => ['ldr', 'view', 'LR_DR'],
            'items' => moduleNavClinicalItems('ldr'),
        ],
        'or' => [
            'label' => 'Operating Room',
            'title' => 'Operating Room',
            'href' => 'or_dashboard.php?module=or',
            'icon' => 'fa-hospital',
            'accent' => 'blue',
            'permission' => ['operating_room', 'view', 'OR'],
            'items' => moduleNavClinicalItems('or'),
        ],
        'micu' => [
            'label' => 'MICU',
            'title' => 'MICU',
            'href' => 'micu_dashboard.php?module=micu',
            'icon' => 'fa-heart-pulse',
            'accent' => 'red',
            'permission' => ['micu', 'view', 'ICU'],
            'items' => moduleNavClinicalItems('micu'),
        ],
        'nicu' => [
            'label' => 'NICU',
            'title' => 'NICU',
            'href' => 'nicu_dashboard.php?module=nicu',
            'icon' => 'fa-baby',
            'accent' => 'teal',
            'permission' => ['nicu', 'view', 'NICU'],
            'items' => moduleNavClinicalItems('nicu'),
        ],
        'ward' => [
            'label' => 'Medical/Surgery Ward',
            'title' => 'Medical/Surgery Ward',
            'href' => 'ward_dashboard.php?module=ward',
            'icon' => 'fa-hospital',
            'accent' => 'green',
            'permission' => ['nursing', 'view', 'WARD_3F'],
            'items' => [
                ['Medical/Surgery Dashboard', 'ward_dashboard.php?module=ward', 'fa-chart-line'],
                ['Medical/Surgery Patients', 'ward_admitted_patients.php?module=ward', 'fa-users'],
                ['Medical/Surgery Bed Tracker', 'ward_beds_view.php?module=ward', 'fa-bed-pulse'],
                ['Trans In / Trans Out Patient', 'trans_out_patient.php?department=WARD&module=ward', 'fa-route'],
                ['Schedule for OR', 'or_schedule.php?module=ward', 'fa-calendar-check'],
                ['Medical/Surgery Reports', 'reports.php?area=ward&module=ward', 'fa-file-medical'],
            ],
        ],
        'ob_pedia_ward' => [
            'label' => 'OB/Pedia Ward',
            'title' => 'OB/Pedia Ward',
            'href' => 'ward_dashboard.php?module=ob_pedia_ward',
            'icon' => 'fa-hospital',
            'accent' => 'teal',
            'permission' => ['nursing', 'view', 'WARD_2F'],
            'items' => [
                ['OB/Pedia Dashboard', 'ward_dashboard.php?module=ob_pedia_ward', 'fa-chart-line'],
                ['OB/Pedia Patients', 'ward_admitted_patients.php?module=ob_pedia_ward', 'fa-users'],
                ['OB/Pedia Bed Tracker', 'ward_beds_view.php?module=ob_pedia_ward', 'fa-bed-pulse'],
                ['Trans In / Trans Out Patient', 'trans_out_patient.php?department=WARD&module=ob_pedia_ward', 'fa-route'],
                ['Schedule for OR', 'or_schedule.php?module=ob_pedia_ward', 'fa-calendar-check'],
                ['OB/Pedia Reports', 'reports.php?area=ward&module=ob_pedia_ward', 'fa-file-medical'],
            ],
        ],
        'beds' => [
            'label' => 'Beds & Census',
            'title' => 'Beds & Census',
            'href' => 'beds.php?module=beds',
            'icon' => 'fa-bed',
            'accent' => 'blue',
            'permission' => ['beds', 'view'],
            'items' => [
                ['ER Bed Tracker', 'er_beds_view.php?module=beds', 'fa-truck-medical'],
                ['Ward Bed Tracker', 'ward_beds_view.php?module=beds', 'fa-bed-pulse'],
                ['Bed Capacity Settings', 'beds.php?module=beds', 'fa-sliders'],
                ['Census Report', 'dashboard.php?module=beds#alerts', 'fa-chart-column'],
                ['Ward Census', 'ward_dashboard.php?module=beds', 'fa-hospital'],
                ['MICU Census', 'micu_bed_tracker.php?module=beds', 'fa-heart-pulse'],
                ['NICU Census', 'nicu_bed_tracker.php?module=beds', 'fa-baby'],
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'title' => 'Reports',
            'href' => 'reports.php?module=reports',
            'icon' => 'fa-chart-bar',
            'accent' => 'orange',
            'permission' => ['reports', 'view'],
            'items' => [
                ['Hospital Statistics', 'dashboard.php?module=reports', 'fa-chart-pie'],
                ['ER Statistics', 'er_statistics_dashboard.php?module=reports', 'fa-truck-medical'],
                ['OPD Reports', 'opd_reports.php?module=reports', 'fa-user-doctor'],
                ['LR/DR Reports', 'ldr_reports.php?module=reports', 'fa-person-pregnant'],
                ['OR Reports', 'or_reports.php?module=reports', 'fa-suitcase-medical'],
                ['MICU Reports', 'micu_reports.php?module=reports', 'fa-heart-pulse'],
                ['NICU Reports', 'nicu_reports.php?module=reports', 'fa-baby'],
                ['Ward Reports', 'reports.php?area=ward&module=reports', 'fa-hospital'],
                ['Nursing Reports', 'reports.php?module=reports', 'fa-file-medical'],
            ],
        ],
        'users' => [
            'label' => 'User Management',
            'title' => 'User Management',
            'href' => 'admin_users.php?module=users',
            'icon' => 'fa-user-shield',
            'accent' => 'teal',
            'permission' => ['user_management', 'view'],
            'items' => [
                ['Users', 'admin_users.php?module=users', 'fa-users-gear'],
                ['Roles and Permissions', 'admin_users.php?module=users#roles', 'fa-key'],
                ['Audit Trail', 'admin_users.php?module=users#audit', 'fa-clipboard-check'],
            ],
        ],
    ];
}

function moduleNavClinicalItems(string $moduleKey): array {
    $defs = clinicalModuleDefinitions();
    if (!isset($defs[$moduleKey])) {
        return [];
    }
    $department = $defs[$moduleKey]['department'] ?? strtoupper($moduleKey);
    $items = [];
    foreach ($defs[$moduleKey]['pages'] as $page => $label) {
        if (stripos($label, 'Transfer') !== false) {
            continue;
        }
        $icon = 'fa-table-list';
        if (stripos($label, 'Dashboard') !== false) { $icon = 'fa-chart-line'; }
        elseif (stripos($label, 'Bed') !== false) { $icon = 'fa-bed-pulse'; }
        elseif (stripos($label, 'Report') !== false) { $icon = 'fa-file-medical'; }
        elseif (stripos($label, 'Schedule') !== false) { $icon = 'fa-calendar-check'; }
        if (stripos($label, 'Report') !== false) {
            $items[] = ['Trans In / Trans Out Patient', 'trans_out_patient.php?department=' . rawurlencode($department), 'fa-route'];
        }
        $items[] = [$label, $page . '?module=' . $moduleKey, $icon];
    }
    return $items;
}

function moduleNavCanAccess(array $module): bool {
    [$permission, $action, $department] = array_pad($module['permission'] ?? [], 3, null);
    return $permission ? hasPermission($permission, $action ?: 'view', $department) : false;
}

function moduleNavVisibleModules(): array {
    $modules = array_filter(moduleNavDefinitions(), 'moduleNavCanAccess');
    foreach ($modules as $key => $module) {
        $modules[$key]['items'] = array_values(array_filter($module['items'], 'moduleNavCanAccessItem'));
    }
    return $modules;
}

function moduleNavCanAccessItem(array $item): bool {
    $label = trim((string) ($item[0] ?? ''));
    if (strcasecmp($label, 'Alerts') === 0 && !isSuperAdmin() && !hasRole(['CSSR_HEAD', 'CSSR_STAFF'])) {
        return false;
    }
    $href = $item[1] ?? '';
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
    $page = basename($path);
    $query = [];
    parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
    if (hasRole(['CSSR_HEAD', 'CSSR_STAFF'])) {
        if ($page !== 'reports.php' && $page !== 'system_menu.php' && $page !== 'account_settings.php') {
            return false;
        }
        if (!empty($query['area'])) {
            return false;
        }
    }
    if ($page === '' || in_array($page, ['system_menu.php', 'account_settings.php'], true)) {
        return true;
    }
    if (function_exists('pagePermissionMap')) {
        $map = pagePermissionMap();
        if (isset($map[$page])) {
            [$module, $action, $department] = array_pad($map[$page], 3, null);
            return hasPermission($module, $action, $department);
        }
    }
    return true;
}

function moduleNavSelectedKey(string $currentPage, array $visibleModules): string {
    $requested = sanitizeText($_GET['module'] ?? '', 40);
    if ($requested !== '' && isset($visibleModules[$requested])) {
        return $requested;
    }

    foreach ($visibleModules as $key => $module) {
        foreach ($module['items'] as $item) {
            $path = parse_url($item[1], PHP_URL_PATH) ?: $item[1];
            if (basename($path) === $currentPage) {
                return $key;
            }
        }
        $path = parse_url($module['href'], PHP_URL_PATH) ?: $module['href'];
        if (basename($path) === $currentPage) {
            return $key;
        }
    }

    return array_key_first($visibleModules) ?: 'dashboard';
}

function moduleNavIsItemActive(string $href, string $currentPage): bool {
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
    if (basename($path) !== $currentPage) {
        return false;
    }
    $query = [];
    parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
    foreach (['module', 'area', 'status', 'destination', 'department'] as $key) {
        if (isset($query[$key]) && (string) ($_GET[$key] ?? '') !== (string) $query[$key]) {
            return false;
        }
    }
    return true;
}
