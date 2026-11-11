<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/procurement-access.php';

requireLogin();

$role = canonicalRole($_SESSION['role'] ?? '');
$department = canonicalDepartment($_SESSION['department'] ?? '');
$departmentHomes = [
    'ER' => 'emergency_room_dashboard.php?module=er',
    'OPD' => 'opd_dashboard.php?module=opd',
    'OR' => 'or_dashboard.php?module=or',
    'LR_DR' => 'ldr_dashboard.php?module=ldr',
    'ICU' => 'micu_dashboard.php?module=micu',
    'NICU' => 'nicu_dashboard.php?module=nicu',
    'WARD_2F' => 'ward_dashboard.php?module=ob_pedia_ward',
    'WARD_3F' => 'ward_dashboard.php?module=ward',
];

if ($role === 'DOCTOR') {
    $nursingHome = 'consultation_queue.php';
} elseif (in_array($role, ['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE'], true)) {
    $nursingHome = 'dashboard.php';
} else {
    $nursingHome = $departmentHomes[$department] ?? 'dashboard.php';
}
$isAdmin = hasRole('SUPER_ADMIN');
$canAccessNursing = hasPermission('nursing', 'view') || hasPermission('patients', 'view');
$canAccessSchedule = in_array($role, ['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE'], true);
$canAccessCssr = $role !== 'ADMITTING_STAFF'
    && (hasPermission('cssr_dashboard', 'view') || hasPermission('cssr_inventory', 'view') || hasPermission('ris_requests', 'view'));
$canAccessProcurement = canAccessProcurementWorkspace(['role'=>$role, 'department'=>$department]);
$canAccessReports = hasPermission('reports', 'view');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__.'/../includes/theme-presentation.php'; echo nursingThemeAssets(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Joni Villanueva General Hospital</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/auth.css">
    <link rel="stylesheet" href="../public/css/menu.css?v=7">
    <link rel="stylesheet" href="../public/css/module-theme.css?v=18">
    <script defer src="../public/js/nursync-ui.js?v=4"></script>
</head>
<body class="menu-page">
    <div class="menu-background-art" aria-hidden="true">
        <span class="menu-bg-cross cross-left">+</span><span class="menu-bg-cross cross-right">+</span><span class="menu-bg-cross cross-gold">+</span>
        <span class="menu-bg-ring ring-left"></span><span class="menu-bg-ring ring-right"></span>
        <svg class="menu-bg-heart" viewBox="0 0 100 100"><path d="M50 82 18 52C-4 27 28 5 50 29 72 5 104 27 82 52Z"/><path class="menu-bg-detail" d="M18 49H35L43 35L53 65L62 46H83"/></svg>
        <svg class="menu-bg-shield" viewBox="0 0 100 100"><path d="M50 8 83 21V48C83 69 66 84 50 93 34 84 17 69 17 48V21Z"/><path class="menu-bg-detail" d="M50 33V66M34 49H66"/></svg>
        <svg viewBox="0 0 1400 200" preserveAspectRatio="none"><path d="M0 110H220L250 85L280 135L320 30L360 175L395 90L425 110H950L990 85L1020 130L1055 45L1090 160L1125 110H1400"/></svg>
    </div>
    <main class="system-menu-shell">
        <section class="system-menu-panel" aria-labelledby="system-menu-title">
            <div class="system-menu-header">
                <div class="menu-brand">
                <img class="auth-logo" src="../public/images/branding/jvgh_seal.png" alt="Joni Villanueva General Hospital logo">
                <div>
                <p class="system-menu-kicker">Joni Villanueva General Hospital</p>
                <h1 id="system-menu-title">NURSYNC</h1>
                <p class="menu-brand-caption">Nursing Management System</p>
                </div>
                </div>
                <div class="menu-preferences">
                    <button type="button" class="ns-theme-toggle" data-nursing-theme-toggle role="switch" aria-checked="false" aria-label="Dark mode for all workspaces"><span data-theme-label>Dark mode</span><span class="ns-theme-track" aria-hidden="true"><span class="ns-theme-thumb"></span></span><span class="ns-theme-state" data-theme-state aria-hidden="true">Off</span></button>
                    <span class="menu-role"><?php echo htmlspecialchars(roleLabel($role)); ?></span>
                </div>
                <div class="menu-welcome-copy"><span>YOUR CONNECTED WORKSPACE</span><h2>Welcome back,<br><em><?php echo htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User'); ?>.</em></h2><p>One nursing system. Everything you need for the work ahead.</p></div>
                <div class="menu-banner-art" aria-hidden="true"><span class="banner-orbit"></span><span class="banner-cross">+</span><span class="banner-small-cross">+</span><span class="banner-dot"></span></div>
            </div>
            <div class="menu-intro">
                <div><span class="menu-eyebrow">MENU</span><h2>Choose your workspace</h2></div>
                <p>Your tools, organized around your day.</p>
            </div>

            <div class="system-choice-grid">
                <?php if ($canAccessNursing): ?>
                    <?php if ($isAdmin): ?>
                    <a class="system-choice-card nursing-choice" href="<?php echo htmlspecialchars($nursingHome); ?>">
                        <span class="system-choice-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>Nursing Information System</strong>
                            <small>Open dashboards, patients, beds, reports, and nursing workflows.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>
                    <?php else: ?>
                        <div class="system-choice-card nursing-choice system-choice-unavailable" aria-disabled="true">
                            <span class="system-choice-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span>
                            <span class="system-choice-copy">
                                <strong>Nursing Information System</strong>
                                <small class="system-choice-status"><i class="bi bi-tools" aria-hidden="true"></i> Under Construction</small>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($canAccessSchedule): ?>
                    <a class="system-choice-card schedule-choice" href="schedule_management.php">
                        <span class="system-choice-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>Nursing Schedule Management</strong>
                            <small>Plan monthly nursing duties, review your schedule, and track requests and hours against the 160-hour reference.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php if ($canAccessNursing): ?>
                    <a class="system-choice-card users-choice" href="employee_documents.php">
                        <span class="system-choice-icon"><i class="bi bi-file-earmark-person" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>Nursing Employee Documents</strong>
                            <small>Manage nursing employment, professional, training, and compliance documents.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php if ($canAccessCssr): ?>
                    <a class="system-choice-card cssr-choice" href="/Nursing_System/CSSR_INVENTORY/">
                        <span class="system-choice-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>CSSR Inventory</strong>
                            <small>Open the Central Sterile Supply Room inventory workspace.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>

                <?php endif; ?>

                <?php if ($canAccessProcurement): ?>
                    <a class="system-choice-card cssr-choice" href="/Nursing_System/PROCUREMENT/">
                        <span class="system-choice-icon"><i class="bi bi-cart-check" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>Procurement</strong>
                            <small>Manage PPMP, Purchase Requests, printing, and pending deliveries.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php if ($canAccessReports): ?>
                    <?php if ($isAdmin): ?>
                        <a class="system-choice-card reports-choice" href="reports.php?module=reports">
                            <span class="system-choice-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                            <span class="system-choice-copy">
                                <strong>Reports</strong>
                                <small>Open nursing, census, movement, and hospital report views.</small>
                            </span>
                            <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <div class="system-choice-card reports-choice system-choice-unavailable" aria-disabled="true">
                            <span class="system-choice-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                            <span class="system-choice-copy">
                                <strong>Reports</strong>
                                <small class="system-choice-status"><i class="bi bi-tools" aria-hidden="true"></i> Under Construction</small>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <a class="system-choice-card reports-choice" href="data_backup.php">
                        <span class="system-choice-icon"><i class="bi bi-database-check" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>Data Backup</strong>
                            <small>Manually create and download complete Nursing and CSSR database backups.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>

                    <a class="system-choice-card users-choice" href="admin_users.php?module=users">
                        <span class="system-choice-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                        <span class="system-choice-copy">
                            <strong>User Management</strong>
                            <small>Manage shared accounts, roles, permissions, and audit access.</small>
                        </span>
                        <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <a class="system-choice-card settings-choice" href="account_settings.php">
                    <span class="system-choice-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                    <span class="system-choice-copy">
                        <strong>Settings</strong>
                        <small>Manage your shared account profile and password for both systems.</small>
                    </span>
                    <i class="bi bi-arrow-up-right system-choice-arrow" aria-hidden="true"></i>
                </a>
            </div>

            <div class="system-menu-actions">
                <span class="menu-footer-note">JVGH <span aria-hidden="true">/</span> One nursing team. One connected workspace.</span>
                <a href="../api/logout.php" class="system-menu-logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout</a>
            </div>
        </section>
    </main>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
