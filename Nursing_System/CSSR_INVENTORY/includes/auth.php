<?php
declare(strict_types=1);

if (!headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'");
    if ($isHttps) header('Strict-Transport-Security: max-age=31536000');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventory_schema.php';
require_once __DIR__ . '/../../includes/cssr-access.php';

const ROLE_ADMIN = 'SUPER_ADMIN';
const ROLE_HEAD = 'CSSR_HEAD';
const ROLE_STAFF = 'CSSR_STAFF';
const ROLE_CHIEF_NURSE = 'CHIEF_NURSE';
const ROLE_SUPERVISOR_NURSE = 'SUPERVISOR_NURSE';
const ROLE_HEAD_NURSE = 'HEAD_NURSE';
const ROLE_STAFF_NURSE = 'STAFF_NURSE';
const ROLE_DOCTOR = 'DOCTOR';
const ROLE_ADMITTING_STAFF = 'ADMITTING_STAFF';
const SHARED_AUTH_DB = 'nursing_system';
const SESSION_TIMEOUT_SECONDS = 1800;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

set_exception_handler(function (Throwable $e): void {
    log_error($e, 'UNHANDLED');
    http_response_code(500);
    echo 'A system error occurred. Please contact the administrator.';
});

function log_error(Throwable $e, string $context = 'APP'): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $message = sprintf(
        "[%s] [%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    error_log($message, 3, $dir . '/app_errors.log');
}

function safe_error_message(Throwable $e, string $fallback = 'A system error occurred. Please try again or contact the administrator.'): string
{
    log_error($e);
    // Preserve explicit business validation; never expose engine errors or paths.
    return ($e instanceof RuntimeException && !($e instanceof PDOException)) || $e instanceof DomainException || $e instanceof InvalidArgumentException
        ? $e->getMessage() : $fallback;
}

function shared_db(): PDO
{
    static $sharedPdo = null;
    if ($sharedPdo instanceof PDO) {
        return $sharedPdo;
    }

    $settings=nursingDatabaseSettings('nursing_system');
    $sharedPdo = new PDO(
        'mysql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$settings['database'].';charset=utf8mb4',
        $settings['user'],
        $settings['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $sharedPdo;
}

function shared_statement(string $sql, array $params = []): PDOStatement
{
    $stmt = shared_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function shared_one(string $sql, array $params = []): ?array
{
    $row = shared_statement($sql, $params)->fetch();
    return $row ?: null;
}

function shared_value(string $sql, array $params = []): mixed
{
    return shared_statement($sql, $params)->fetchColumn();
}

function shared_department_label(?string $department): string
{
    $map = [
        'ER' => 'EMERGENCY ROOM',
        'OPD' => 'OPD',
        'OR' => 'OPERATING ROOM',
        'LR_DR' => 'DELIVERY ROOM',
        'ICU' => 'ICU',
        'NICU' => 'NICU',
        'WARD_2F' => 'WARD-MEDICAL/SURGERY',
        'WARD_3F' => 'WARD-OB/PEDIA',
        'CSSR' => 'CSSR',
        'ASC' => 'ASC',
        'ONCOLOGY' => 'ONCOLOGY',
        'CANCER_TREATMENT_UNIT' => 'ONCOLOGY',
        'NSO' => 'NSO',
    ];
    $department = strtoupper(trim((string) $department));
    return $map[$department] ?? 'CSSR';
}

function shared_canonical_role(?string $role): string
{
    $role = strtoupper(trim((string) $role));
    return match ($role) {
        'NURSE', 'NURSING_ATTENDANT', 'MIDWIFE' => ROLE_STAFF_NURSE,
        'CLERK' => ROLE_ADMITTING_STAFF,
        'ASSISTANT_CHIEF_NURSE', 'SUPERVISOR' => ROLE_SUPERVISOR_NURSE,
        'CSSR_HEAD' => ROLE_STAFF,
        default => $role,
    };
}

function department_id_for_shared_department(?string $department): ?int
{
    $departmentName = shared_department_label($department);
    $departmentId = (int) db_value(
        'SELECT id FROM departments WHERE department_name=? AND status="Active" LIMIT 1',
        [$departmentName]
    );
    return $departmentId > 0 ? $departmentId : null;
}

function sync_local_cssr_user(array $sharedUser): array
{
    $firstName = trim((string) ($sharedUser['first_name'] ?? ''));
    $lastName = trim((string) ($sharedUser['last_name'] ?? ''));
    $fullName = trim((string) ($sharedUser['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = trim($firstName . ' ' . $lastName);
    }
    if ($fullName === '') {
        $fullName = (string) $sharedUser['username'];
    }

    $departmentId = department_id_for_shared_department($sharedUser['department'] ?? null);
    $username = (string) $sharedUser['username'];
    $sharedRole = shared_canonical_role($sharedUser['role'] ?? '');
    // The local column uses canonical enum values, not display labels.
    $role = in_array($sharedRole, [ROLE_ADMIN, ROLE_STAFF, ROLE_CHIEF_NURSE, ROLE_SUPERVISOR_NURSE, ROLE_HEAD_NURSE, ROLE_STAFF_NURSE, ROLE_DOCTOR, ROLE_ADMITTING_STAFF], true)
        ? $sharedRole : ROLE_STAFF_NURSE;
    $status = (string) ($sharedUser['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'Active' : 'Inactive';

    $localUser = db_one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
    if ($localUser) {
        $profile = ['first_name'=>$firstName,'last_name'=>$lastName,'full_name'=>$fullName,'email'=>$sharedUser['email'] ?? null,'department_id'=>$departmentId,'role'=>$role,'status'=>$status];
        $changed = false;
        foreach ($profile as $field => $value) if ((string)($localUser[$field] ?? '') !== (string)$value) $changed = true;
        if ($changed) db_statement(
            'UPDATE users SET first_name=?, last_name=?, full_name=?, email=?, department_id=?, role=?, status=? WHERE id=?',
            [$firstName, $lastName, $fullName, $sharedUser['email'] ?? null, $departmentId, $role, $status, (int) $localUser['id']]
        );
        $localId = (int) $localUser['id'];
    } else {
        db_statement(
            'INSERT INTO users (first_name, last_name, full_name, username, email, password, department_id, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$firstName, $lastName, $fullName, $username, $sharedUser['email'] ?? null, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $departmentId, $role, $status]
        );
        $localId = (int) db()->lastInsertId();
    }

    $user = db_one(
        'SELECT u.*, d.department_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ?
         LIMIT 1',
        [$localId]
    );

    return [
        'id' => $user['id'] ?? $localId,
        'shared_user_id' => $sharedUser['user_id'] ?? ($sharedUser['id'] ?? null),
        'full_name' => $user['full_name'] ?? $fullName,
        'username' => $user['username'] ?? $username,
        'role' => $sharedRole,
        'original_role' => strtoupper(trim((string) ($sharedUser['role'] ?? ''))),
        'local_role' => $user['role'] ?? $role,
        'department_id' => $user['department_id'] ?? $departmentId,
        'department_name' => $user['department_name'] ?? shared_department_label($sharedUser['department'] ?? null),
    ];
}

function is_cssr_inventory_admin(): bool
{
    $user = current_user() ?? [];
    return ($user['role'] ?? '') === ROLE_ADMIN || isCssrAttendantAdmin($user);
}

function shared_has_permission(string $moduleName, string $action = 'view'): bool
{
    $role = current_user()['role'] ?? '';
    if ($role === ROLE_ADMIN) {
        return true;
    }
    // User Activity is reserved for system administrators, even if a role has broader inventory permissions.
    if ($moduleName === 'audit_trail') {
        return false;
    }
    if (cssrAttendantPermission(current_user() ?? [], $moduleName, $action, true)) {
        return true;
    }

    $columnMap = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'approve' => 'can_approve',
        'print' => 'can_print',
    ];
    $column = $columnMap[$action] ?? 'can_view';
    // Per-request only: role changes take effect on the next request, never a TTL.
    static $permissionCache = [];
    $cacheKey = json_encode([current_user(), $moduleName, $column]);
    $cacheable = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    if ($cacheable && array_key_exists($cacheKey, $permissionCache)) return $permissionCache[$cacheKey];
    try {
        $allowed = shared_value(
            "SELECT {$column}
             FROM role_permissions
             WHERE role = ? AND module_name IN (?, '*')
             ORDER BY module_name = ? DESC
             LIMIT 1",
            [$role, $moduleName, $moduleName]
        );
        $result = (int) $allowed === 1;
        if ($cacheable) $permissionCache[$cacheKey] = $result;
        return $result;
    } catch (Throwable $e) {
        log_error($e, 'SHARED_PERMISSION');
        return false;
    }
}

function cssr_landing_page(): string
{
    if (shared_has_permission('cssr_dashboard', 'view') || shared_has_permission('cssr_inventory', 'view')) {
        return 'pages/dashboard.php';
    }
    if (shared_has_permission('ris_requests', 'view') || shared_has_permission('ris_requests', 'create')) {
        return 'pages/item_requests.php';
    }
    return '/Nursing_System/pages/system_menu.php';
}

function cssr_optional_schema_query(string $sql): bool
{
    try {
        db_statement($sql);
        return true;
    } catch (Throwable $e) {
        log_error($e, 'SECURITY_SCHEMA');
        return false;
    }
}

function ensure_security_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db_statement(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(80) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                last_attempt DATETIME NOT NULL,
                UNIQUE KEY uniq_login_attempt (username, ip_address),
                INDEX idx_locked_until (locked_until)
            ) ENGINE=InnoDB'
        );
        cssr_optional_schema_query('ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL');
        cssr_optional_schema_query(
            "UPDATE users
             SET role = CASE role
                WHEN 'Admin' THEN 'SUPER_ADMIN'
                WHEN 'Department Head' THEN 'CSSR_STAFF'
                WHEN 'Department Staff' THEN 'CSSR_STAFF'
                WHEN 'CSSR Head' THEN 'CSSR_STAFF'
                WHEN 'CSSR Staff' THEN 'CSSR_STAFF'
                WHEN 'Viewer/Auditor' THEN 'CSSR_STAFF'
                ELSE UPPER(role)
             END"
        );
        cssr_optional_schema_query("UPDATE users SET role='CSSR_STAFF' WHERE role='CSSR_HEAD'");
        cssr_optional_schema_query("ALTER TABLE users MODIFY role ENUM('SUPER_ADMIN','CHIEF_NURSE','SUPERVISOR_NURSE','HEAD_NURSE','STAFF_NURSE','DOCTOR','ADMITTING_STAFF','CSSR_STAFF') NOT NULL");
        $userColumns = array_column(db_all('SHOW COLUMNS FROM users'), 'Field');
        if (!in_array('first_name', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD first_name VARCHAR(80) NULL AFTER id');
        }
        if (!in_array('middle_name', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD middle_name VARCHAR(80) NULL AFTER first_name');
        }
        if (!in_array('last_name', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD last_name VARCHAR(80) NULL AFTER middle_name');
        }
        if (!in_array('email', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD email VARCHAR(150) NULL AFTER username');
        }
        if (!in_array('phone_number', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD phone_number VARCHAR(50) NULL AFTER email');
        }
        if (!in_array('birthday', $userColumns, true)) {
            cssr_optional_schema_query('ALTER TABLE users ADD birthday DATE NULL AFTER phone_number');
        }
        cssr_optional_schema_query(
            "UPDATE users
             SET first_name = COALESCE(NULLIF(first_name, ''), SUBSTRING_INDEX(full_name, ' ', 1)),
                 last_name = COALESCE(NULLIF(last_name, ''), TRIM(SUBSTRING(full_name, LENGTH(SUBSTRING_INDEX(full_name, ' ', 1)) + 1)))
             WHERE full_name <> '' AND (first_name IS NULL OR first_name = '' OR last_name IS NULL OR last_name = '')"
        );
    } catch (Throwable $e) {
        log_error($e, 'SECURITY_SCHEMA');
    }
}

// Existing installations must not perform DDL or role migrations during requests.
// Maintenance operators may opt in from a CLI process; never from a web request.
if (PHP_SAPI === 'cli' && getenv('NURSING_RUN_SCHEMA_SETUP') === '1') {
    ensure_security_schema();
    ensure_cssr_inventory_schema();
}

function bootstrap_local_access(): void
{
    if (isset($_SESSION['user'])) {
        return;
    }

    $currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($currentScript === 'logout.php') {
        return;
    }

    try {
        if (isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])) {
            $sharedUser = shared_one(
                "SELECT user_id, id, username, password, email, first_name, last_name, full_name, role, department, status
                 FROM users
                 WHERE user_id = ? AND username = ? AND status = 'ACTIVE' AND COALESCE(is_active, 1) = 1
                 LIMIT 1",
                [(int) $_SESSION['user_id'], (string) $_SESSION['username']]
            );
            if (!$sharedUser) {
                return;
            }

            $_SESSION['user'] = sync_local_cssr_user($sharedUser);
            $_SESSION['last_activity'] = time();
            return;
        }
    } catch (Throwable $e) {
        log_error($e, 'LOCAL_ACCESS');
    }
}

bootstrap_local_access();

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }
    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());
    if (time() - $lastActivity > SESSION_TIMEOUT_SECONDS) {
        log_activity('TIMEOUT', 'Authentication', 'Session expired after inactivity');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        header('Location: /Nursing_System/pages/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        log_activity('CSRF_BLOCK', 'Security', 'Invalid CSRF token');
        http_response_code(403);
        exit('Invalid security token. Please go back, refresh the page, and try again.');
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /Nursing_System/pages/login.php');
        exit;
    }
    refresh_cssr_session_access();
    enforce_session_timeout();
    verify_csrf_token();
    enforce_current_page_permission();
}

function refresh_cssr_session_access(): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }
    $sharedUser = shared_one(
        "SELECT user_id,id,username,password,email,first_name,last_name,full_name,role,department,status
         FROM users WHERE user_id=? AND status='ACTIVE' AND COALESCE(is_active,1)=1 LIMIT 1",
        [$userId]
    );
    if (!$sharedUser) {
        $_SESSION = [];
        session_destroy();
        header('Location: /Nursing_System/pages/login.php');
        exit;
    }
    $_SESSION['username'] = $sharedUser['username'];
    $_SESSION['role'] = shared_canonical_role($sharedUser['role'] ?? '');
    $_SESSION['department'] = $sharedUser['department'] ?? '';
    $_SESSION['user'] = sync_local_cssr_user($sharedUser);
}

function cssr_page_permission_map(): array
{
    return [
        'dashboard.php' => ['cssr_dashboard', 'view'],
        'items.php' => ['cssr_inventory', 'view'],
        'item_details.php' => ['cssr_inventory', 'view'],
        'stock_in.php' => ['cssr_inventory', 'create'],
        'inbound.php' => ['cssr_inventory', 'create'],
        'stock_out.php' => ['cssr_inventory', 'edit'],
        'get_stock.php' => ['cssr_inventory', 'create'],
        'storage_requests.php' => ['cssr_inventory', 'create'],
        'storage_room_1.php' => ['cssr_inventory', 'view'],
        'department_returns.php' => ['ris_requests', 'create'],
        'stock_adjustment.php' => ['cssr_inventory', 'edit'],
        'cssr_available_stock.php' => ['cssr_inventory', 'view'],
        'stock_pos.php' => ['cssr_inventory', 'view'],
        'stock_card.php' => ['cssr_inventory', 'view'],
        'inventory_adjustment.php' => ['cssr_inventory', 'edit'],
        'expiry_monitoring.php' => ['cssr_inventory', 'view'],
        'reorder_alerts.php' => ['cssr_inventory', 'view'],
        'suppliers.php' => ['cssr_inventory', 'edit'],
        'departments.php' => ['cssr_inventory', 'edit'],
        'item_requests.php' => ['ris_requests', 'view'],
        'requisitions.php' => ['ris_requests', 'view'],
        'request_queue.php' => ['cssr_inventory', 'view'],
        'request_history.php' => ['ris_requests', 'view'],
        'released_requests.php' => ['ris_requests', 'view'],
        'reports.php' => ['reports', 'view'],
        'monthly_report.php' => ['reports', 'view'],
        'bin_card.php' => ['cssr_inventory', 'view'],
        'activity_logs.php' => ['audit_trail', 'view'],
        'backup_restore.php' => ['user_management', 'view'],
        'settings.php' => ['ris_requests', 'view'],
        'users.php' => ['user_management', 'view'],
    ];
}

function enforce_current_page_permission(): void
{
    static $checking = false;
    if ($checking) {
        return;
    }

    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((current_user()['role'] ?? '') === ROLE_ADMITTING_STAFF
        && str_contains($scriptPath, '/CSSR_INVENTORY/pages/')) {
        log_activity('RESTRICTED_ACCESS', 'Security', "Blocked Clerk from CSSR Inventory page: {$page}");
        header('Location: /Nursing_System/pages/system_menu.php');
        exit;
    }
    $map = cssr_page_permission_map();
    if (!isset($map[$page])) {
        return;
    }

    $checking = true;
    [$module, $action] = $map[$page];
    if (!shared_has_permission($module, $action)) {
        log_activity('RESTRICTED_ACCESS', 'Security', "Blocked direct CSSR URL: {$page}");
        $checking = false;
        header('Location: /Nursing_System/pages/system_menu.php');
        exit;
    }
    $checking = false;
}

function require_roles(array $roles): void
{
    require_login();
    $role = current_user()['role'] ?? '';
    $allowed = in_array($role, $roles, true)
        || is_cssr_inventory_admin()
        || (in_array(ROLE_STAFF, $roles, true) && shared_has_permission('ris_requests', 'view'));
    if (!$allowed) {
        log_activity('RESTRICTED_ACCESS', 'Security', 'Blocked role ' . $role);
        http_response_code(403);
        include __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">Access denied for your role.</div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

function is_cssr_department(): bool
{
    return strcasecmp(trim((string) (current_user()['department_name'] ?? '')), 'CSSR') === 0;
}

function can_view_cssr_request_lists(): bool
{
    return (current_user()['role'] ?? '') === ROLE_ADMIN || is_cssr_department();
}

function can_view_admin_operational(): bool
{
    if ((current_user()['role'] ?? '') === ROLE_ADMITTING_STAFF) {
        return false;
    }
    return shared_has_permission('cssr_dashboard', 'view')
        || shared_has_permission('cssr_inventory', 'view')
        || shared_has_permission('reports', 'view');
}

function require_admin_or_cssr(): void
{
    require_login();
    if (!can_view_admin_operational()) {
        http_response_code(403);
        include __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">Access denied for your role.</div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

function can_manage(): bool
{
    return shared_has_permission('cssr_inventory', 'edit')
        || shared_has_permission('ris_requests', 'approve');
}

function can_transact(): bool
{
    return shared_has_permission('cssr_inventory', 'create')
        || shared_has_permission('ris_requests', 'create');
}

function can_view_reports(): bool
{
    return shared_has_permission('reports', 'view') || shared_has_permission('cssr_inventory', 'view');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function log_activity(string $action, string $module, string $details = ''): void
{
    $userId = current_user()['id'] ?? null;
    try {
        db_statement('INSERT INTO activity_logs (user_id, action, module, details) VALUES (?, ?, ?, ?)', [$userId, $action, $module, $details]);
    } catch (Throwable $e) {
        log_error($e, 'ACTIVITY_LOG');
    }
}

function upload_item_image(array $file, ?string $oldImage = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldImage;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must not exceed 2MB.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (preg_match('/\.(php|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi)(\.|$)/i', $originalName) || !in_array($extension, $allowed, true)) {
        throw new RuntimeException('Only jpg, jpeg, png, and webp images are allowed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Invalid image file.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $filename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/items/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $target = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save uploaded image.');
    }

    if ($oldImage && is_file(__DIR__ . '/../uploads/items/' . $oldImage)) {
        @unlink(__DIR__ . '/../uploads/items/' . $oldImage);
    }
    return $filename;
}

function login_lock_row(string $username, string $ip): ?array
{
    return db_one('SELECT * FROM login_attempts WHERE username=? AND ip_address=? LIMIT 1', [$username, $ip]);
}

function login_is_locked(string $username, string $ip): bool
{
    $row = login_lock_row($username, $ip);
    return $row && !empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time();
}

function record_failed_login(string $username, string $ip): void
{
    $row = login_lock_row($username, $ip);
    $attempts = $row ? ((int) $row['attempts'] + 1) : 1;
    $lockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60) : null;
    db_statement(
        'INSERT INTO login_attempts (username, ip_address, attempts, locked_until, last_attempt)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE attempts=VALUES(attempts), locked_until=VALUES(locked_until), last_attempt=NOW()',
        [$username, $ip, $attempts, $lockedUntil]
    );
}

function clear_login_attempts(string $username, string $ip): void
{
    db_statement('DELETE FROM login_attempts WHERE username=? AND ip_address=?', [$username, $ip]);
}

function verify_current_admin_password(string $password): bool
{
    $username = current_user()['username'] ?? '';
    $hash = shared_value("SELECT password FROM users WHERE username=? AND role=? AND status='ACTIVE'", [$username, ROLE_ADMIN]);
    return is_string($hash) && password_verify($password, $hash);
}

function stock_status(array $item): string
{
    $stock = (int) $item['current_stock'];
    $min = max(1, (int) $item['minimum_stock']);
    if ($stock <= 0) {
        return 'OUT OF STOCK';
    }
    if ($stock <= max(1, (int) floor($min / 2))) {
        return 'CRITICAL';
    }
    if ($stock <= $min) {
        return 'LOW STOCK';
    }
    return 'NORMAL';
}

