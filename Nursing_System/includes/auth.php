<?php
// Session and Authentication Handler

session_start();

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
    
    return in_array($_SESSION['role'], $requiredRoles);
}

function homePageForRole($role = null) {
    $role = $role ?? ($_SESSION['role'] ?? '');
    if ($role === 'doctor') {
        return 'consultation_queue.php';
    }
    if ($role === 'admission_staff') {
        return 'admitted_patients.php';
    }

    return 'dashboard.php';
}

function roleOptions() {
    return [
        'chief_nurse' => 'Chief Nurse',
        'nurse_supervisor' => 'Nurse Supervisor',
        'head_nurse' => 'Head Nurse',
        'staff_nurse' => 'Staff Nurse',
        'nursing_attendant' => 'Nursing Attendant',
        'nurse' => 'Nurse',
        'doctor' => 'Doctor',
        'admission_staff' => 'Admission Staff',
        'admin' => 'Admin'
    ];
}

function nurseRoles() {
    return ['nurse', 'chief_nurse', 'nurse_supervisor', 'head_nurse', 'staff_nurse', 'nursing_attendant'];
}

function doctorRoles() {
    return ['doctor'];
}

function admissionRoles() {
    return ['admission_staff'];
}

function admittingPatientCreationRoles() {
    return array_merge(nurseRoles(), admissionRoles());
}

function dispositionDecisionRoles() {
    return doctorRoles();
}

function admittedPatientDischargeRoles() {
    return ['staff_nurse', 'nursing_attendant'];
}

function registrationRoleOptions() {
    return [
        'chief_nurse' => 'Chief Nurse',
        'nurse_supervisor' => 'Nurse Supervisor',
        'head_nurse' => 'Head Nurse',
        'staff_nurse' => 'Staff Nurse',
        'nursing_attendant' => 'Nursing Attendant',
        'doctor' => 'Doctor',
        'admission_staff' => 'Admission Staff'
    ];
}

function departmentOptions() {
    return [
        'emergency_room' => 'Emergency Room',
        'out_patient_department' => 'Out Patient Department'
    ];
}

function roleLabel($role) {
    $roles = roleOptions();
    return $roles[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function departmentLabel($department) {
    $departments = departmentOptions();
    return $departments[$department] ?? ucwords(str_replace('_', ' ', $department));
}

function ensureUserRoleSchema($conn) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return true;
    }

    $accountSchemaResult = ensureUserAccountSchema($conn);
    if ($accountSchemaResult !== true) {
        return false;
    }

    $roleValues = array_map(function ($role) use ($conn) {
        return "'" . $conn->real_escape_string($role) . "'";
    }, array_keys(roleOptions()));

    return $conn->query("ALTER TABLE users MODIFY role ENUM(" . implode(',', $roleValues) . ") NOT NULL");
}

function ensureUserAccountSchema($conn) {
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM users");
    if (!$columnsResult) {
        return false;
    }

    while ($column = $columnsResult->fetch_assoc()) {
        $columns[$column['Field']] = true;
    }

    if (!isset($columns['department'])) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN department ENUM('emergency_room', 'out_patient_department') NULL AFTER role")) {
            return false;
        }
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

        $adminCheck = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        if ($adminCheck && $adminCheck->num_rows > 0) {
            return true;
        }

        $username = 'admin';
        $password = hashPassword('admin123');
        $email = 'admin@nursing-system.local';
        $firstName = 'System';
        $lastName = 'Admin';
        $role = 'admin';
        $department = 'emergency_room';

        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, first_name, last_name, role, department, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE role = 'admin', is_active = TRUE
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sssssss", $username, $password, $email, $firstName, $lastName, $role, $department);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        return true;
    }
}

function hasDepartment($requiredDepartments) {
    return isLoggedIn();
}

function requireDepartment($requiredDepartments) {
    requireLogin();
}

function requireRole($requiredRoles) {
    requireLogin();
    if (!hasRole($requiredRoles) && !hasRole('admin')) {
        header('Location: ../pages/' . homePageForRole());
        exit();
    }
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../pages/login.php');
        exit();
    }
}

// Redirect to home if already logged in
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: ../pages/dashboard.php');
        exit();
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: ../pages/login.php');
    exit();
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

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}
?>
