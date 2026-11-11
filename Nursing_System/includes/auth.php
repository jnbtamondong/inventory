<?php
// Session, Authentication, and Security Handler
require_once __DIR__ . '/cssr-access.php';
require_once __DIR__ . '/theme-presentation.php';

const SESSION_IDLE_TIMEOUT_SECONDS = 1800;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900;

function currentRequestPath() {
    return str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
}

function isApiRequest() {
    return strpos(currentRequestPath(), '/api/') !== false;
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

startSecureSession();

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function securityHeaders() {
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: blob: https:; font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self' https://psgc.gitlab.io; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

securityHeaders();

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function csrfMetaTag() {
    return '<meta name="csrf-token" content="' . h(csrfToken()) . '">';
}

function rejectBadRequest($message = 'Invalid request.') {
    http_response_code(400);
    if (isApiRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
    } else {
        exit($message);
    }
    exit();
}

function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($token)) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'CSRF Rejected', 'Rejected POST without a valid CSRF token');
        http_response_code(419);
        rejectBadRequest('Security token expired. Please refresh the page and try again.');
    }
}

function injectCsrfTokens($html) {
    $html = nursingInjectThemeAssets($html);
    if ($html === '' || stripos($html, '<form') === false) {
        return $html;
    }

    $tokenField = csrfField();
    $html = preg_replace_callback('/<form\b([^>]*)>/i', function ($matches) use ($tokenField) {
        $tag = $matches[0];
        if (stripos($tag, 'method="POST"') === false && stripos($tag, "method='POST'") === false && stripos($tag, 'method=POST') === false) {
            return $tag;
        }
        return $tag . $tokenField;
    }, $html);

    if (stripos($html, '</head>') !== false && stripos($html, 'name="csrf-token"') === false) {
        $html = preg_replace('/<\/head>/i', csrfMetaTag() . "\n</head>", $html, 1);
    }

    return $html;
}

if (!isApiRequest() && !headers_sent()) {
    ob_start('injectCsrfTokens');
}

function sanitizeText($value, $maxLength = 255) {
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function validDateString($date, $format = 'Y-m-d') {
    $parsed = DateTime::createFromFormat($format, (string) $date);
    return $parsed && $parsed->format($format) === $date;
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][$type] = $message;
}

function consumeFlashMessages(&$error, &$success) {
    if (!empty($_SESSION['flash_messages']['error'])) {
        $error = $_SESSION['flash_messages']['error'];
    }
    if (!empty($_SESSION['flash_messages']['success'])) {
        $success = $_SESSION['flash_messages']['success'];
    }
    unset($_SESSION['flash_messages']);
}

function redirectAfterPost($url = null) {
    $url = $url ?: basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    header('Location: ' . $url, true, 303);
    exit();
}

function ensureSecuritySchema($conn) {
    if (PHP_SAPI !== 'cli' || getenv('NURSING_RUN_SCHEMA_SETUP') !== '1') return true;
    static $done = false;
    if ($done || !$conn) {
        return true;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS audit_logs (
            audit_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            username VARCHAR(100) NULL,
            role VARCHAR(50) NULL,
            department VARCHAR(50) NULL,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(100) NULL,
            patient_id INT NULL,
            encounter_id VARCHAR(30) NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            user_agent TEXT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_action (action),
            INDEX idx_audit_created (created_at)
        )
    ");

    $auditColumns = [];
    $auditColumnResult = $conn->query("SHOW COLUMNS FROM audit_logs");
    if ($auditColumnResult) {
        while ($column = $auditColumnResult->fetch_assoc()) {
            $auditColumns[$column['Field']] = true;
        }
    }
    $auditAlter = [
        'role' => "ALTER TABLE audit_logs ADD COLUMN role VARCHAR(50) NULL AFTER username",
        'department' => "ALTER TABLE audit_logs ADD COLUMN department VARCHAR(50) NULL AFTER role",
        'module' => "ALTER TABLE audit_logs ADD COLUMN module VARCHAR(100) NULL AFTER action",
        'patient_id' => "ALTER TABLE audit_logs ADD COLUMN patient_id INT NULL AFTER module",
        'encounter_id' => "ALTER TABLE audit_logs ADD COLUMN encounter_id VARCHAR(30) NULL AFTER patient_id",
        'old_value' => "ALTER TABLE audit_logs ADD COLUMN old_value TEXT NULL AFTER encounter_id",
        'new_value' => "ALTER TABLE audit_logs ADD COLUMN new_value TEXT NULL AFTER old_value",
        'user_agent' => "ALTER TABLE audit_logs ADD COLUMN user_agent TEXT NULL AFTER new_value",
    ];
    foreach ($auditAlter as $column => $sql) {
        if (!isset($auditColumns[$column])) {
            $conn->query($sql);
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            attempt_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success BOOLEAN NOT NULL DEFAULT FALSE,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_attempt_lookup (username, ip_address, attempted_at)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            role VARCHAR(50) NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 0,
            can_create TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            can_approve TINYINT(1) NOT NULL DEFAULT 0,
            can_print TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_module (role, module_name),
            INDEX idx_role_permissions_role (role),
            INDEX idx_role_permissions_module (module_name)
        )
    ");

    seedDefaultRolePermissions($conn);

    $done = true;
    return true;
}

function clientIpAddress() {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function logSecurityEvent($conn, $action, $details = '', $userId = null) {
    if (!$conn) {
        return false;
    }

    try {
        ensureSecuritySchema($conn);
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $username = $_SESSION['username'] ?? null;
        $role = $_SESSION['role'] ?? null;
        $department = $_SESSION['department'] ?? null;
        $ip = clientIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, role, department, ip_address, action, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $userId, $username, $role, $department, $ip, $action, $userAgent, $details);
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
        return false;
    }
}

function isLoginLocked($conn, $username) {
    ensureSecuritySchema($conn);
    $ip = clientIpAddress();
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SECONDS);
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS failed_attempts
        FROM login_attempts
        WHERE username = ? AND ip_address = ? AND success = FALSE AND attempted_at >= ?
    ");
    $stmt->bind_param("sss", $username, $ip, $since);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row['failed_attempts'] ?? 0) >= LOGIN_MAX_ATTEMPTS;
}

function recordLoginAttempt($conn, $username, $success) {
    ensureSecuritySchema($conn);
    $ip = clientIpAddress();
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $successValue = $success ? 1 : 0;
    $stmt->bind_param("ssi", $username, $ip, $successValue);
    $stmt->execute();

    if ($success) {
        $cleanup = $conn->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ? AND success = FALSE");
        $cleanup->bind_param("ss", $username, $ip);
        $cleanup->execute();
    }
}

function enforceIdleTimeout() {
    if (!isLoggedIn()) {
        return;
    }

    $lastActivity = $_SESSION['last_activity'] ?? time();
    if ((time() - (int) $lastActivity) > SESSION_IDLE_TIMEOUT_SECONDS) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'Session Timeout', 'User was logged out due to inactivity');
        destroySession();
        header('Location: ../pages/login.php?timeout=1');
        exit();
    }

    $_SESSION['last_activity'] = time();
}

requireCsrfToken();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check user role permission
function hasRole($requiredRoles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($requiredRoles)) {
        $requiredRoles = array($requiredRoles);
    }

    $currentRole = canonicalRole($_SESSION['role'] ?? '');
    $requiredRoles = array_map('canonicalRole', $requiredRoles);
    return in_array($currentRole, $requiredRoles, true);
}

function canonicalRole($role) {
    $map = [
        'admin' => 'SUPER_ADMIN',
        'chief_nurse' => 'CHIEF_NURSE',
        'nurse_supervisor' => 'SUPERVISOR_NURSE',
        'assistant_chief_nurse' => 'SUPERVISOR_NURSE',
        'ASSISTANT_CHIEF_NURSE' => 'SUPERVISOR_NURSE',
        'supervisor' => 'SUPERVISOR_NURSE',
        'SUPERVISOR' => 'SUPERVISOR_NURSE',
        'head_nurse' => 'HEAD_NURSE',
        'staff_nurse' => 'STAFF_NURSE',
        'nursing_attendant' => 'STAFF_NURSE',
        'NURSING_ATTENDANT' => 'STAFF_NURSE',
        'nurse' => 'STAFF_NURSE',
        'NURSE' => 'STAFF_NURSE',
        'midwife' => 'STAFF_NURSE',
        'MIDWIFE' => 'STAFF_NURSE',
        'clerk' => 'ADMITTING_STAFF',
        'CLERK' => 'ADMITTING_STAFF',
        'doctor' => 'DOCTOR',
        'admission_staff' => 'ADMITTING_STAFF',
        'cssr_head' => 'CSSR_HEAD',
        'cssr_staff' => 'CSSR_STAFF',
    ];
    $role = trim((string) $role);
    return $map[$role] ?? strtoupper($role);
}

function canonicalDepartment($department) {
    $map = [
        'nursing' => 'NSO',
        'NURSING' => 'NSO',
        'nursing_service' => 'NSO',
        'NURSING_SERVICE' => 'NSO',
        'Nursing Service' => 'NSO',
        'NURSING SERVICE' => 'NSO',
        'emergency_room' => 'ER',
        'out_patient_department' => 'OPD',
        'operating_room' => 'OR',
        'labor_delivery_room' => 'LR_DR',
        'ward_department' => 'WARD_3F',
        'medical_surgery_ward' => 'WARD_3F',
        'ob_pedia_ward' => 'WARD_2F',
        'ICU' => 'ICU',
        'MICU' => 'ICU',
        'micu' => 'ICU',
        'LR-DR' => 'LR_DR',
        'LR/DR' => 'LR_DR',
        'OB/Pedia Ward' => 'WARD_2F',
        'Medical/Surgery Ward' => 'WARD_3F',
        'NICU' => 'NICU',
        'cssr' => 'CSSR',
    ];
    $department = trim((string) $department);
    return $map[$department] ?? strtoupper($department);
}

function isSuperAdmin() {
    return hasRole('SUPER_ADMIN');
}

function allSystemRoles() {
    return [
        'SUPER_ADMIN',
        'CHIEF_NURSE',
        'SUPERVISOR_NURSE',
        'HEAD_NURSE',
        'STAFF_NURSE',
        'DOCTOR',
        'ADMITTING_STAFF',
        'CSSR_STAFF',
    ];
}

function homePageForRole($role = null, $department = null) {
    $role = canonicalRole($role ?? ($_SESSION['role'] ?? ''));
    $department = canonicalDepartment($department ?? ($_SESSION['department'] ?? ''));

    if ($role === 'SUPER_ADMIN') {
        return 'system_menu.php';
    }
    if (in_array($role, ['CSSR_HEAD', 'CSSR_STAFF'], true)) {
        return 'system_menu.php';
    }
    if ($role === 'SUPER_ADMIN' || $role === 'CHIEF_NURSE' || $role === 'SUPERVISOR_NURSE') {
        return 'dashboard.php';
    }

    if (in_array($department, ['WARD_2F', 'WARD_3F', 'ICU', 'NICU'], true)) {
        return 'ward_dashboard.php';
    }
    if ($department === 'OPD') {
        return 'opd_dashboard.php';
    }
    if ($department === 'OR') {
        return 'or_dashboard.php';
    }
    if ($department === 'LR_DR') {
        return 'ldr_dashboard.php';
    }
    if ($department === 'ICU') {
        return 'micu_dashboard.php';
    }
    if ($department === 'NICU') {
        return 'nicu_dashboard.php';
    }
    if ($department === 'ER') {
        return 'emergency_room_dashboard.php';
    }

    return 'dashboard.php';
}

function homeUrlForRole($role = null, $department = null): string {
    $role = canonicalRole($role ?? ($_SESSION['role'] ?? ''));
    if (in_array($role, ['CSSR_HEAD', 'CSSR_STAFF'], true)) {
        return '/Nursing_System/CSSR_INVENTORY/pages/dashboard.php';
    }
    return '/Nursing_System/pages/' . homePageForRole($role, $department);
}

function roleOptions() {
    return [
        'SUPER_ADMIN' => 'Super Admin',
        'CHIEF_NURSE' => 'Chief Nurse',
        'ASSISTANT_CHIEF_NURSE' => 'Assistant Chief Nurse',
        'SUPERVISOR' => 'Supervisor',
        'HEAD_NURSE' => 'Head Nurse',
        'NURSE' => 'Nurse',
        'NURSING_ATTENDANT' => 'Nursing Attendant',
        'DOCTOR' => 'Doctor',
        'CLERK' => 'Clerk',
        'MIDWIFE' => 'Midwife',
        'CSSR_STAFF' => 'CSSR Staff',
    ];
}

function nurseRoles() {
    return ['CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE', 'STAFF_NURSE', 'ADMITTING_STAFF'];
}

function doctorRoles() {
    return ['DOCTOR'];
}

function admissionRoles() {
    return ['ADMITTING_STAFF', 'STAFF_NURSE', 'HEAD_NURSE', 'SUPERVISOR_NURSE'];
}

function erStaffRoles() {
    return admissionRoles();
}

function admittingPatientCreationRoles() {
    return array_merge(nurseRoles(), admissionRoles());
}

function dispositionDecisionRoles() {
    return doctorRoles();
}

function admittedPatientDischargeRoles() {
    return array_merge(['STAFF_NURSE'], doctorRoles());
}

function registrationRoleOptions() {
    return roleOptions();
}

function departmentOptions() {
    return [
        'WARD_3F' => 'Medical/Surgery Ward',
        'WARD_2F' => 'OB/Pedia Ward',
        'ER' => 'EMERGENCY ROOM',
        'OR' => 'OPERATING ROOM',
        'LR_DR' => 'DELIVERY ROOM',
        'OPD' => 'OPD',
        'CSSR' => 'CSSR',
        'NSO' => 'NSO',
        'NICU' => 'NICU',
        'ASC' => 'ASC',
        'ONCOLOGY' => 'ONCOLOGY',
    ];
}

function expandedDepartmentAliases($departments) {
    $departments = is_array($departments) ? $departments : [$departments];
    $expanded = [];

    foreach ($departments as $department) {
        $department = canonicalDepartment($department);
        $expanded[] = $department;
        if ($department === 'WARD_2F') {
            $expanded = array_merge($expanded, ['ob_pedia_ward']);
        }
        if ($department === 'WARD_3F') {
            $expanded = array_merge($expanded, ['ward_department', 'medical_surgery_ward']);
        }
        if ($department === 'ER') {
            $expanded = array_merge($expanded, ['emergency_room']);
        }
        if ($department === 'OPD') {
            $expanded = array_merge($expanded, ['out_patient_department']);
        }
        if ($department === 'OR') {
            $expanded = array_merge($expanded, ['operating_room']);
        }
        if ($department === 'LR_DR') {
            $expanded = array_merge($expanded, ['labor_delivery_room']);
        }
        if ($department === 'CSSR') {
            $expanded = array_merge($expanded, ['cssr']);
        }
    }

    return array_values(array_unique($expanded));
}

function roleLabel($role) {
    $roles = roleOptions();
    if (strtoupper(trim((string) $role)) === 'STAFF_NURSE') {
        return $roles['NURSE'];
    }
    return $roles[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function departmentLabel($department) {
    $departments = departmentOptions();
    return $departments[$department] ?? ucwords(str_replace('_', ' ', $department));
}

function isUsersView($conn) {
    $result = $conn->query("SHOW FULL TABLES LIKE 'users'");
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $table = $result->fetch_assoc();
    return strtoupper($table['Table_type'] ?? end($table) ?? '') === 'VIEW';
}

function optionalSchemaQuery($conn, $sql) {
    try {
        return (bool) $conn->query($sql);
    } catch (mysqli_sql_exception $e) {
        error_log('[OPTIONAL SCHEMA] ' . $e->getMessage() . ' SQL: ' . $sql);
        return false;
    }
}

function ensureUserRoleSchema($conn) {
    if (PHP_SAPI !== 'cli' || getenv('NURSING_RUN_SCHEMA_SETUP') !== '1') return true;
    ensureSecuritySchema($conn);
    $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return true;
    }

    $accountSchemaResult = ensureUserAccountSchema($conn);
    if ($accountSchemaResult !== true) {
        return false;
    }

    if (isUsersView($conn)) {
        return true;
    }

    optionalSchemaQuery($conn, "ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL");
    optionalSchemaQuery($conn, "
        UPDATE users
        SET role = CASE role
            WHEN 'admin' THEN 'SUPER_ADMIN'
            WHEN 'chief_nurse' THEN 'CHIEF_NURSE'
            WHEN 'nurse_supervisor' THEN 'SUPERVISOR_NURSE'
            WHEN 'head_nurse' THEN 'HEAD_NURSE'
            WHEN 'staff_nurse' THEN 'NURSE'
            WHEN 'STAFF_NURSE' THEN 'NURSE'
            WHEN 'nursing_attendant' THEN 'NURSING_ATTENDANT'
            WHEN 'NURSING_ATTENDANT' THEN 'NURSING_ATTENDANT'
            WHEN 'nurse' THEN 'NURSE'
            WHEN 'NURSE' THEN 'NURSE'
            WHEN 'doctor' THEN 'DOCTOR'
            WHEN 'admission_staff' THEN 'ADMITTING_STAFF'
            ELSE UPPER(role)
        END
    ");

    $roleValues = array_map(function ($role) use ($conn) {
        return "'" . $conn->real_escape_string($role) . "'";
    }, allSystemRoles());

    optionalSchemaQuery($conn, "UPDATE users SET role='CSSR_STAFF' WHERE role='CSSR_HEAD'");
    optionalSchemaQuery($conn, "ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL");
    return true;
}

function ensureUserAccountSchema($conn) {
    if (PHP_SAPI !== 'cli' || getenv('NURSING_RUN_SCHEMA_SETUP') !== '1') return true;
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM users");
    if (!$columnsResult) {
        return false;
    }

    while ($column = $columnsResult->fetch_assoc()) {
        $columns[$column['Field']] = true;
    }

    if (isUsersView($conn)) {
        return isset($columns['department']) && isset($columns['is_active']);
    }

    $departmentValues = array_map(function ($department) use ($conn) {
        return "'" . $conn->real_escape_string($department) . "'";
    }, array_keys(departmentOptions()));

    if (!isset($columns['department'])) {
        if (!optionalSchemaQuery($conn, "ALTER TABLE users ADD COLUMN department VARCHAR(50) NULL AFTER role")) {
            return false;
        }
    } else {
        optionalSchemaQuery($conn, "ALTER TABLE users MODIFY department VARCHAR(50) NULL");
        optionalSchemaQuery($conn, "
            UPDATE users
            SET department = CASE department
                WHEN 'emergency_room' THEN 'ER'
                WHEN 'out_patient_department' THEN 'OPD'
                WHEN 'operating_room' THEN 'OR'
                WHEN 'ward_department' THEN 'WARD_3F'
                WHEN 'medical_surgery_ward' THEN 'WARD_3F'
                WHEN 'ob_pedia_ward' THEN 'WARD_2F'
                WHEN 'icu' THEN 'ICU'
                WHEN 'nicu' THEN 'NICU'
                WHEN 'cssr' THEN 'CSSR'
                ELSE department
            END
            WHERE department IS NOT NULL
        ");
        optionalSchemaQuery($conn, "ALTER TABLE users MODIFY department VARCHAR(50) NULL");
    }

    if (!isset($columns['id'])) {
        $conn->query("ALTER TABLE users ADD COLUMN id INT NULL AFTER user_id");
        $conn->query("UPDATE users SET id = user_id WHERE id IS NULL");
        $conn->query("ALTER TABLE users ADD UNIQUE KEY unique_users_id (id)");
    }

    if (!isset($columns['full_name'])) {
        $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL AFTER id");
        $conn->query("UPDATE users SET full_name = TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) WHERE full_name IS NULL OR full_name = ''");
    }

    $employeeColumns = [
        'employee_id' => "VARCHAR(50) NULL AFTER full_name",
        'middle_name' => "VARCHAR(50) NULL AFTER first_name",
        'suffix' => "VARCHAR(20) NULL AFTER last_name",
        'position' => "VARCHAR(100) NULL AFTER suffix",
        'employment_status' => "VARCHAR(30) NULL AFTER position",
        'date_hired' => "DATE NULL AFTER employment_status",
    ];
    foreach ($employeeColumns as $field => $definition) {
        if (!isset($columns[$field])) {
            optionalSchemaQuery($conn, "ALTER TABLE users ADD COLUMN {$field} {$definition}");
        }
    }
    if (!isset($columns['employee_id'])) {
        optionalSchemaQuery($conn, "ALTER TABLE users ADD UNIQUE KEY unique_employee_id (employee_id)");
    }

    if (!isset($columns['status'])) {
        $conn->query("ALTER TABLE users ADD COLUMN status ENUM('ACTIVE','INACTIVE','RESIGNED','VOID','CANCELLED','ARCHIVED','REVERSED') NOT NULL DEFAULT 'ACTIVE' AFTER department");
        $conn->query("UPDATE users SET status = IF(COALESCE(is_active, 1) = 1, 'ACTIVE', 'INACTIVE')");
    } else {
        $statusColumn=$conn->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch_assoc();
        if(!str_contains(strtoupper((string)($statusColumn['Type']??'')),'RESIGNED'))$conn->query("ALTER TABLE users MODIFY status ENUM('ACTIVE','INACTIVE','RESIGNED','VOID','CANCELLED','ARCHIVED','REVERSED') NOT NULL DEFAULT 'ACTIVE'");
        $conn->query("UPDATE users SET status = IF(COALESCE(is_active, 1) = 1, 'ACTIVE', 'INACTIVE') WHERE status NOT IN ('RESIGNED','VOID','CANCELLED','ARCHIVED','REVERSED')");
    }

    if (!isset($columns['is_active'])) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER phone")) {
            if (!$conn->query("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE")) {
                return false;
            }
        }
    }

    return true;
}

function ensureDefaultAdminUser($conn) {
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return true;
        }

        $schemaResult = ensureUserAccountSchema($conn);
        if ($schemaResult !== true) {
            return false;
        }

        $adminCheck = $conn->query("SELECT user_id FROM users WHERE role = 'SUPER_ADMIN' LIMIT 1");
        if ($adminCheck && $adminCheck->num_rows > 0) {
            return true;
        }

        $username = 'admin';
        $password = hashPassword('admin123');
        $email = 'admin@nursing-system.local';
        $firstName = 'System';
        $lastName = 'Admin';
        $role = 'SUPER_ADMIN';
        $department = null;

        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, first_name, last_name, full_name, role, department, status, is_active)
            VALUES (?, ?, ?, ?, ?, CONCAT(?, ' ', ?), ?, ?, 'ACTIVE', TRUE)
            ON DUPLICATE KEY UPDATE role = 'SUPER_ADMIN', department = NULL, status = 'ACTIVE', is_active = TRUE
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sssssssss", $username, $password, $email, $firstName, $lastName, $firstName, $lastName, $role, $department);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        return true;
    }
}

function hasDepartment($requiredDepartments) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $requiredDepartments = expandedDepartmentAliases($requiredDepartments);

    return in_array(canonicalDepartment($_SESSION['department'] ?? ''), $requiredDepartments, true);
}

function requireDepartment($requiredDepartments) {
    requireLogin();
    if (!isDepartmentAllowed($requiredDepartments)) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', 'Department blocked: ' . currentRequestPath());
        header('Location: ../pages/' . homePageForRole());
        exit();
    }
}

function requireRole($requiredRoles) {
    requireLogin();
    if (!hasRole($requiredRoles) && !isSuperAdmin()) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', 'Role blocked: ' . currentRequestPath());
        header('Location: ../pages/' . homePageForRole());
        exit();
    }
}

function isErLimitedUser(): bool {
    if (!isLoggedIn()) {
        return false;
    }

    return hasDepartment('ER')
        && hasRole(array_merge(nurseRoles(), admissionRoles()))
        && !hasRole(['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE']);
}

function isWardLimitedUser(): bool {
    if (!isLoggedIn()) {
        return false;
    }

    return hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU'])
        && hasRole(array_merge(nurseRoles(), admissionRoles()))
        && !hasRole(['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE']);
}

function isDepartmentAllowed($requiredDepartments = null) {
    if (isSuperAdmin() || hasRole(['CHIEF_NURSE', 'CSSR_HEAD', 'CSSR_STAFF'])) {
        return true;
    }
    if ($requiredDepartments === null || $requiredDepartments === []) {
        return true;
    }
    return hasDepartment($requiredDepartments);
}

function defaultRolePermissions() {
    $full = ['can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_approve' => 1, 'can_print' => 1];
    $viewPrint = ['can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_approve' => 0, 'can_print' => 1];
    $nursingEdit = ['can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_approve' => 0, 'can_print' => 1];
    $risCreate = ['can_view' => 1, 'can_create' => 1, 'can_edit' => 0, 'can_delete' => 0, 'can_approve' => 0, 'can_print' => 1];
    $cssrProcess = ['can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_approve' => 1, 'can_print' => 1];

    return [
        'SUPER_ADMIN' => ['*' => $full, 'user_management' => $full],
        'CHIEF_NURSE' => [
            'nursing' => $viewPrint,
            'patients' => $viewPrint,
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $viewPrint,
            'ldr' => $viewPrint,
            'operating_room' => $viewPrint,
            'micu' => $viewPrint,
            'nicu' => $viewPrint,
            'patient_movements' => $viewPrint,
            'cssr_dashboard' => $viewPrint,
            'cssr_inventory' => $viewPrint,
            'ris_requests' => $viewPrint,
        ],
        'SUPERVISOR_NURSE' => [
            'nursing' => $nursingEdit,
            'patients' => $nursingEdit,
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $nursingEdit,
            'ldr' => $nursingEdit,
            'operating_room' => $nursingEdit,
            'micu' => $nursingEdit,
            'nicu' => $nursingEdit,
            'patient_movements' => $nursingEdit,
            'ris_requests' => $risCreate,
        ],
        'HEAD_NURSE' => [
            'nursing' => $nursingEdit,
            'patients' => $nursingEdit,
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $nursingEdit,
            'ldr' => $nursingEdit,
            'operating_room' => $nursingEdit,
            'micu' => $nursingEdit,
            'nicu' => $nursingEdit,
            'patient_movements' => $nursingEdit,
            'ris_requests' => $risCreate,
        ],
        'STAFF_NURSE' => [
            'nursing' => $nursingEdit,
            'patients' => $nursingEdit,
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $nursingEdit,
            'ldr' => $nursingEdit,
            'operating_room' => $nursingEdit,
            'micu' => $nursingEdit,
            'nicu' => $nursingEdit,
            'patient_movements' => $nursingEdit,
            'ris_requests' => $risCreate,
        ],
        'DOCTOR' => [
            'nursing' => $viewPrint,
            'patients' => ['can_view' => 1, 'can_create' => 0, 'can_edit' => 1, 'can_delete' => 0, 'can_approve' => 1, 'can_print' => 1],
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $viewPrint,
            'patient_movements' => $viewPrint,
        ],
        'ADMITTING_STAFF' => [
            'nursing' => $nursingEdit,
            'patients' => $nursingEdit,
            'beds' => $viewPrint,
            'reports' => $viewPrint,
            'opd' => $nursingEdit,
            'ldr' => $nursingEdit,
            'operating_room' => $nursingEdit,
            'micu' => $nursingEdit,
            'nicu' => $nursingEdit,
            'patient_movements' => $nursingEdit,
            'ris_requests' => $risCreate,
        ],
        'CSSR_STAFF' => [
            'cssr_dashboard' => $cssrProcess,
            'cssr_inventory' => $cssrProcess,
            'ris_requests' => $cssrProcess,
            'reports' => $viewPrint,
        ],
    ];
}

function seedDefaultRolePermissions($conn) {
    static $seeded = false;
    if ($seeded || !$conn) {
        return;
    }
    $seeded = true;

    $stmt = $conn->prepare("
        INSERT INTO role_permissions (role, module_name, can_view, can_create, can_edit, can_delete, can_approve, can_print)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            can_view = VALUES(can_view),
            can_create = VALUES(can_create),
            can_edit = VALUES(can_edit),
            can_delete = VALUES(can_delete),
            can_approve = VALUES(can_approve),
            can_print = VALUES(can_print)
    ");
    if (!$stmt) {
        return;
    }

    foreach (defaultRolePermissions() as $role => $modules) {
        foreach ($modules as $module => $permissions) {
            $stmt->bind_param(
                "ssiiiiii",
                $role,
                $module,
                $permissions['can_view'],
                $permissions['can_create'],
                $permissions['can_edit'],
                $permissions['can_delete'],
                $permissions['can_approve'],
                $permissions['can_print']
            );
            $stmt->execute();
        }
    }
}

function hasPermission($moduleName, $action = 'view', $department = null) {
    if (!isLoggedIn()) {
        return false;
    }
    if (!isDepartmentAllowed($department)) {
        return false;
    }
    if (isSuperAdmin()) {
        return true;
    }

    if (cssrAttendantPermission($_SESSION, $moduleName, $action)) {
        return true;
    }

    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) {
        return false;
    }

    ensureSecuritySchema($conn);
    $columnMap = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'approve' => 'can_approve',
        'print' => 'can_print',
    ];
    $column = $columnMap[$action] ?? 'can_view';
    $role = canonicalRole($_SESSION['role'] ?? '');
    $stmt = $conn->prepare("SELECT $column AS allowed FROM role_permissions WHERE role = ? AND module_name IN (?, '*') ORDER BY module_name = ? DESC LIMIT 1");
    $stmt->bind_param("sss", $role, $moduleName, $moduleName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row['allowed'] ?? 0) === 1;
}

function requirePermission($moduleName, $action = 'view', $department = null) {
    requireLogin();
    if (!hasPermission($moduleName, $action, $department)) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', "Permission blocked: {$moduleName}.{$action} " . currentRequestPath());
        header('Location: ../pages/' . homePageForRole());
        exit();
    }
}

// APIs must enforce the same module permissions as their corresponding pages.
function requireApiPermission(string $moduleName, string $action = 'view'): void {
    requireLogin();
    if (!hasPermission($moduleName, $action)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'You do not have permission for this action.']);
        exit;
    }
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../pages/login.php');
        exit();
    }

    refreshAuthenticatedUserAccess();
    enforceIdleTimeout();
    enforceCurrentPagePermission();
}

function refreshAuthenticatedUserAccess(): void {
    $conn = $GLOBALS['conn'] ?? null;
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if (!$conn || $userId <= 0) {
        return;
    }

    $stmt = $conn->prepare("SELECT username,first_name,last_name,role,department,status,is_active FROM users WHERE user_id=? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !(int) $user['is_active'] || ($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
        $_SESSION = [];
        session_destroy();
        header('Location: ../pages/login.php');
        exit();
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['role'] = canonicalRole($user['role']);
    $_SESSION['original_role'] = strtoupper(trim((string) $user['role']));
    $_SESSION['department'] = canonicalDepartment($user['department'] ?? '');

    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['username'] = $user['username'];
        $_SESSION['user']['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['user']['role'] = canonicalRole($user['role']);
        $_SESSION['user']['original_role'] = $_SESSION['original_role'];
        $_SESSION['user']['department_name'] = $user['department'] ?? '';
    }
}

function pagePermissionMap() {
    $map = [
        'dashboard.php' => ['nursing', 'view'],
        'emergency_room_dashboard.php' => ['nursing', 'view', 'ER'],
        'er_statistics_dashboard.php' => ['nursing', 'view', 'ER'],
        'ward_dashboard.php' => ['nursing', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']],
        'ward_statistics_dashboard.php' => ['nursing', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']],
        'patient_queue.php' => ['patients', 'view'],
        'patient_queue_view.php' => ['patients', 'view'],
        'patient_live_tracker.php' => ['patients', 'view'],
        'bed_tracker.php' => ['beds', 'view'],
        'patients.php' => ['patients', 'view'],
        'patient_registration.php' => ['patients', 'create', 'ER'],
        'er_patient_tracker.php' => ['patients', 'view', 'ER'],
        'patient_detail.php' => ['patients', 'view'],
        'patient_log.php' => ['patients', 'view'],
        'patient_history.php' => ['patients', 'view'],
        'admitted_list.php' => ['patients', 'view'],
        'admitted_patients.php' => ['patients', 'view'],
        'ward_admitted_patients.php' => ['patients', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']],
        'triage.php' => ['patients', 'edit', 'ER'],
        'er_record.php' => ['patients', 'print', 'ER'],
        'beds.php' => ['beds', 'view'],
        'er_beds.php' => ['beds', 'view', 'ER'],
        'er_beds_view.php' => ['beds', 'view', 'ER'],
        'ward_beds.php' => ['beds', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']],
        'ward_beds_view.php' => ['beds', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']],
        'reports.php' => ['reports', 'view'],
        'monthly_report.php' => ['reports', 'view'],
        'top_cases.php' => ['reports', 'view'],
        'nursing_bed_tracker_report.php' => ['reports', 'view'],
        'nursing_hospital_census_report.php' => ['reports', 'view'],
        'nursing_er_statistics_report.php' => ['reports', 'view'],
        'nursing_patient_census_summary.php' => ['reports', 'view'],
        'nursing_kpi_report.php' => ['reports', 'view'],
        'nursing_ward_discharge_tat_report.php' => ['reports', 'view'],
        'nursing_or_safe_operation_report.php' => ['reports', 'view'],
        'nursing_monthly_report.php' => ['reports', 'view'],
        'nursing_notes.php' => ['patients', 'view'],
        'newborn_record.php' => ['ldr', 'view', 'LR_DR'],
        'postpartum_monitoring.php' => ['ldr', 'view', 'LR_DR'],
        'opd_dashboard.php' => ['opd', 'view', 'OPD'],
        'opd_patients.php' => ['opd', 'view', 'OPD'],
        'opd_consultation.php' => ['opd', 'view', 'OPD'],
        'opd_for_er.php' => ['opd', 'view', 'OPD'],
        'opd_for_admission.php' => ['opd', 'view', 'OPD'],
        'opd_for_or.php' => ['opd', 'view', 'OPD'],
        'opd_follow_up.php' => ['opd', 'view', 'OPD'],
        'opd_reports.php' => ['opd', 'view', 'OPD'],
        'movement_history.php' => ['patient_movements', 'view'],
        'admin_users.php' => ['user_management', 'view'],
        'data_backup.php' => ['user_management', 'view'],
        'employee_documents.php' => ['nursing', 'view'],
    ];
    $clinicalHelper = __DIR__ . '/clinical_modules.php';
    if (is_file($clinicalHelper)) {
        require_once $clinicalHelper;
        $map = array_merge($map, clinicalModulePageMap());
    }
    return $map;
}

function enforceCurrentPagePermission() {
    static $checking = false;
    if ($checking || isApiRequest()) {
        return;
    }

    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($page, ['login.php', 'reset_password.php', 'system_menu.php', 'nursing_home.php', 'account_settings.php'], true)) {
        return;
    }
    $map = pagePermissionMap();
    // Nursing workforce requests are shared by clinical departments. Each page
    // enforces ownership, request eligibility and its independent approval scope.
    if(in_array($page,['my_requests.php','nursing_requests.php','duty_exchange.php','request_application.php','exchange_application.php'],true)){
        $workforceRole=strtoupper((string)($_SESSION['original_role']??$_SESSION['role']??''));
        if(in_array($workforceRole,['NURSE','STAFF_NURSE','NURSING_ATTENDANT','HEAD_NURSE','SUPERVISOR','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE','SUPER_ADMIN'],true))return;
        http_response_code(403);exit('Nursing request access required.');
    }
    // Ward pages share routes, but a query-string module cannot change account scope.
    if (in_array($page,['ward_dashboard.php','ward_statistics_dashboard.php','ward_admitted_patients.php','ward_beds.php','ward_beds_view.php','discharge_patients.php'],true)) {
        $context=$_GET['module']??($_POST['module']??null);
        if($context!==null&&!is_string($context)){http_response_code(400);exit('Invalid department context.');}
        $contextDepartment=['ward'=>'WARD_3F','ob_pedia_ward'=>'WARD_2F'][$context??'']??null;
        if ($contextDepartment && !isDepartmentAllowed($contextDepartment)) {http_response_code(403);exit('Access denied for this department.');}
        $assigned=canonicalDepartment($_SESSION['department']??'');
        if(!$contextDepartment&&in_array($assigned,['WARD_2F','WARD_3F'],true)) {
            $_GET['module']=$assigned==='WARD_2F'?'ob_pedia_ward':'ward';
            if($_SERVER['REQUEST_METHOD']==='POST')$_POST['module']=$_GET['module'];
        }
    }

    if (isErLimitedUser()) {
        $erAllowedPages = [
            'patient_live_tracker.php',
            'bed_tracker.php',
            'patient_queue.php',
            'patient_queue_view.php',
            'triage.php',
            'admitted_patients.php',
            'emergency_room_dashboard.php',
            'er_statistics_dashboard.php',
            'patients.php',
            'patient_registration.php',
            'er_patient_tracker.php',
            'patient_detail.php',
            'patient_log.php',
            'er_beds.php',
            'er_beds_view.php',
            'admitted_list.php',
            'reports.php',
            'monthly_report.php',
            'er_record.php',
            'movement_history.php',
            'employee_documents.php',
        ];
        if (!in_array($page, $erAllowedPages, true) && ($map[$page][0]??'')!=='reports') {
            logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', 'ER limited user blocked: ' . currentRequestPath());
            header('Location: ../pages/emergency_room_dashboard.php');
            exit();
        }
    }

    if (isWardLimitedUser()) {
        $wardAllowedPages = [
            'patient_live_tracker.php',
            'bed_tracker.php',
            'patient_detail.php',
            'micu_monitoring.php',
            'nicu_patients.php',
            'ward_dashboard.php',
            'ward_statistics_dashboard.php',
            'ward_beds.php',
            'ward_beds_view.php',
            'ward_admitted_patients.php',
            'discharge_patients.php',
            'reports.php',
            'monthly_report.php',
            'movement_history.php',
            'trans_out_patient.php',
            'employee_documents.php',
        ];
        if (!in_array($page, $wardAllowedPages, true) && !isset(clinicalModulePageMap()[$page]) && ($map[$page][0]??'')!=='reports') {
            logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', 'Ward limited user blocked: ' . currentRequestPath());
            header('Location: ../pages/ward_dashboard.php');
            exit();
        }
    }

    $map = pagePermissionMap();
    if (!isset($map[$page])) {
        return;
    }

    $checking = true;
    if ($page === 'dashboard.php' && (
        hasPermission('nursing', 'view')
        || hasPermission('patients', 'view')
        || hasPermission('ris_requests', 'view')
        || hasPermission('cssr_dashboard', 'view')
    )) {
        $checking = false;
        return;
    }

    [$module, $action, $department] = array_pad($map[$page], 3, null);
    if (!hasPermission($module, $action, $department)) {
        logSecurityEvent($GLOBALS['conn'] ?? null, 'Restricted Access Attempt', "Direct URL blocked: {$page}");
        $checking = false;
        header('Location: ../pages/' . homePageForRole());
        exit();
    }
    $checking = false;
}

// Redirect to home if already logged in
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: /Nursing_System/pages/system_menu.php');
        exit();
    }
}

// Logout function
function logout() {
    logSecurityEvent($GLOBALS['conn'] ?? null, 'Logout', 'User logged out');
    destroySession();
    header('Location: ../pages/login.php');
    exit();
}

function destroySession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }
    session_destroy();
}

// Get user info
function getUserInfo($userId, $conn) {
    $stmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name, role, department FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Validate password
function validatePassword($password, $hash) {
    return password_verify($password, $hash);
}

function passwordPolicyError($password, $username = '', $email = '') {
    $password = (string) $password;
    if (strlen($password) < 10) {
        return 'Password must be at least 10 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include at least one symbol.';
    }

    $lowerPassword = strtolower($password);
    foreach ([$username, $email] as $part) {
        $part = strtolower(trim((string) $part));
        if ($part !== '' && strlen($part) >= 3 && str_contains($lowerPassword, $part)) {
            return 'Password must not contain the username or email.';
        }
    }

    $commonPasswords = ['password', 'password123', 'admin123', 'qwerty', 'letmein', '123456', '123456789'];
    if (in_array($lowerPassword, $commonPasswords, true)) {
        return 'Please choose a less common password.';
    }

    return '';
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}
?>
