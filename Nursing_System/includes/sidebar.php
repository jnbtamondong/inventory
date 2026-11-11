<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$liveTrackerPages = ['patient_live_tracker.php'];
$bedTrackerPages = ['bed_tracker.php'];
$doctorPages = ['consultation_queue.php'];
$emergencyRoomPages = ['patients.php', 'triage.php', 'admitted_patients.php', 'admitted_list.php', 'discharge_patients.php', 'patient_detail.php'];
$patientLogPages = ['patient_log.php'];
$patientHistoryPages = ['patient_history.php'];
$admittedPages = ['admitted_patients.php'];
$admittedListPages = ['admitted_list.php'];
$dischargePages = ['discharge_patients.php'];
$reportPages = ['reports.php', 'monthly_report.php', 'top_cases.php'];
$adminPages = ['admin_users.php'];
$patientAction = $_GET['action'] ?? '';
$isAddingPatient = $currentPage === 'patients.php' && in_array($patientAction, ['choose', 'add', 'old', 'old_edit'], true);

function sidebarActive($pages, $currentPage) {
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($currentPage, $pages, true) ? ' class="active"' : '';
}

$isAdmin = hasRole('admin');
$isNurse = hasRole(nurseRoles());
$isDoctor = hasRole(doctorRoles());
$isAdmission = hasRole(admissionRoles());
$canViewBedTracker = $isAdmin || $isAdmission || hasRole('staff_nurse');
$canAddPatient = $isAdmin || hasRole(admittingPatientCreationRoles());

?>
<aside class="sidebar">
    <ul class="menu">
        <?php if ($isAdmin || $isNurse || $isAdmission): ?>
            <li><a href="dashboard.php"<?php echo sidebarActive('dashboard.php', $currentPage); ?>>Dashboard</a></li>
            <li><a href="patient_live_tracker.php"<?php echo sidebarActive($liveTrackerPages, $currentPage); ?>>Patient Live Tracker</a></li>
        <?php endif; ?>

        <?php if ($canViewBedTracker): ?>
            <li><a href="bed_tracker.php"<?php echo sidebarActive($bedTrackerPages, $currentPage); ?>>Bed Tracker</a></li>
        <?php endif; ?>

        <?php if ($isAdmin || $isNurse || $isDoctor || $isAdmission): ?>
            <li class="has-submenu <?php echo in_array($currentPage, $emergencyRoomPages, true) ? 'open' : ''; ?>">
                <a href="#">Emergency Room</a>
                <ul class="submenu">
                    <?php if ($isAdmin || $isNurse || $isDoctor): ?>
                        <li><a href="patients.php"<?php echo ($currentPage === 'patients.php' && !$isAddingPatient) || $currentPage === 'patient_detail.php' ? ' class="active"' : ''; ?>>Active Patients</a></li>
                    <?php endif; ?>
                    <?php if ($canAddPatient): ?>
                        <li><a href="patients.php?action=choose"<?php echo $isAddingPatient ? ' class="active"' : ''; ?>>Add New Patient</a></li>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isNurse): ?>
                        <li><a href="triage.php"<?php echo sidebarActive('triage.php', $currentPage); ?>>Triage</a></li>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isAdmission): ?>
                        <li><a href="admitted_patients.php"<?php echo sidebarActive($admittedPages, $currentPage); ?>>Admission</a></li>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isAdmission || $isNurse || $isDoctor): ?>
                        <li><a href="admitted_list.php"<?php echo sidebarActive($admittedListPages, $currentPage); ?>>Admitted Patients</a></li>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <li><a href="discharge_patients.php"<?php echo sidebarActive($dischargePages, $currentPage); ?>>Discharged Patients</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isDoctor): ?>
            <li class="has-submenu <?php echo in_array($currentPage, $doctorPages, true) ? 'open' : ''; ?>">
                <a href="#">Doctor</a>
                <ul class="submenu">
                    <li><a href="consultation_queue.php"<?php echo sidebarActive('consultation_queue.php', $currentPage); ?>>Consultation Queue</a></li>
                </ul>
            </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isNurse || $isDoctor || $isAdmission): ?>
            <li class="has-submenu <?php echo in_array($currentPage, array_merge($patientLogPages, $patientHistoryPages), true) ? 'open' : ''; ?>">
                <a href="#">Patients</a>
                <ul class="submenu">
                    <?php if ($isAdmin || $isNurse || $isAdmission): ?>
                        <li><a href="patient_log.php"<?php echo sidebarActive($patientLogPages, $currentPage); ?>>Patient Masterlist</a></li>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isDoctor): ?>
                        <li><a href="patient_history.php"<?php echo sidebarActive($patientHistoryPages, $currentPage); ?>>Patient History</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isAdmission): ?>
            <li><a href="beds.php"<?php echo sidebarActive('beds.php', $currentPage); ?>>Beds</a></li>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <li class="has-submenu <?php echo in_array($currentPage, $adminPages, true) ? 'open' : ''; ?>">
                <a href="#">Admin</a>
                <ul class="submenu">
                    <li><a href="admin_users.php"<?php echo sidebarActive('admin_users.php', $currentPage); ?>>User Management</a></li>
                </ul>
            </li>

            <li class="has-submenu <?php echo in_array($currentPage, $reportPages, true) ? 'open' : ''; ?>">
                <a href="#">Reports</a>
                <ul class="submenu">
                    <li><a href="reports.php"<?php echo sidebarActive('reports.php', $currentPage); ?>>Daily Report</a></li>
                    <li><a href="monthly_report.php"<?php echo sidebarActive('monthly_report.php', $currentPage); ?>>Monthly Report</a></li>
                    <li><a href="top_cases.php"<?php echo sidebarActive('top_cases.php', $currentPage); ?>>Top 10 Cases</a></li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</aside>
