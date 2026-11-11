<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('SUPER_ADMIN');
ensureUserRoleSchema($conn);
ensureDefaultAdminUser($conn);

$conn->query("CREATE TABLE IF NOT EXISTS user_departments (code VARCHAR(50) PRIMARY KEY,name VARCHAR(100) NOT NULL,status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS user_roles (code VARCHAR(50) PRIMARY KEY,name VARCHAR(100) NOT NULL,status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS user_job_titles (name VARCHAR(100) PRIMARY KEY,status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (request_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,status ENUM('PENDING','RESOLVED','CANCELLED') NOT NULL DEFAULT 'PENDING',requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,resolved_at DATETIME NULL,resolved_by INT NULL,INDEX idx_reset_request_status(status),INDEX idx_reset_request_user(user_id),FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE)");
$conn->query("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL");
$conn->query("ALTER TABLE users MODIFY department VARCHAR(50) NULL");
$conn->query("ALTER TABLE users MODIFY email VARCHAR(100) NULL");
foreach (roleOptions() as $code=>$name) { $s=$conn->prepare("INSERT INTO user_roles(code,name,status) VALUES(?,?,'ACTIVE') ON DUPLICATE KEY UPDATE name=VALUES(name),status='ACTIVE'");$s->bind_param('ss',$code,$name);$s->execute(); }
$conn->query("UPDATE user_roles SET name='Nurse',status='INACTIVE' WHERE UPPER(code)='STAFF_NURSE'");
foreach (departmentOptions() as $code=>$name) { $s=$conn->prepare("INSERT IGNORE INTO user_departments(code,name) VALUES(?,?)");$s->bind_param('ss',$code,$name);$s->execute(); }
$conn->query("UPDATE user_departments SET status='INACTIVE' WHERE UPPER(code) IN ('NURSING','NURSING_SERVICE','NURSING SERVICE')");
$defaultJobTitles = ['NURSE I','NURSE II','NURSE V','NURSE VI','NURSING ATTENDANT I','NURSING ATTENDANT II','RESPIRATORY THERAPIST','ADMINISTRATIVE AIDE IV','ADMINISTRATIVE AIDE V','ADMINISTRATIVE AIDE VI','ADMINISTRATIVE ASSISTANT I','ADMINISTRATIVE ASSISTANT II','MIDWIFE I'];
foreach ($defaultJobTitles as $name) { $s=$conn->prepare("INSERT IGNORE INTO user_job_titles(name) VALUES(?)");$s->bind_param('s',$name);$s->execute(); }

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$roleOptions = [];
$result=$conn->query("SELECT code,name FROM user_roles WHERE status='ACTIVE' ORDER BY name");while($row=$result->fetch_assoc())$roleOptions[$row['code']]=$row['name'];
$staffRoleOptions = $roleOptions;
$departmentOptions = [];
$result=$conn->query("SELECT code,name FROM user_departments WHERE status='ACTIVE' ORDER BY name");while($row=$result->fetch_assoc())$departmentOptions[$row['code']]=$row['name'];
$jobTitleOptions = [];
$result=$conn->query("SELECT name FROM user_job_titles WHERE status='ACTIVE' ORDER BY name");while($row=$result->fetch_assoc())$jobTitleOptions[$row['name']]=$row['name'];
$rolesWithoutDepartment = ['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);

    if ($action === 'add_department') {
        $code=strtoupper(preg_replace('/[^A-Z0-9]+/','_',trim((string)($_POST['department_code']??''))));$name=sanitizeText($_POST['department_name']??'',100);
        if($code===''||$name==='')$error='Department code and name are required.';
        else{$s=$conn->prepare("INSERT INTO user_departments(code,name,status) VALUES(?,?,'ACTIVE') ON DUPLICATE KEY UPDATE name=VALUES(name),status='ACTIVE'");$s->bind_param('ss',$code,$name);$s->execute();$success='Department added successfully.';}
    } elseif ($action === 'add_job_title') {
        $name = strtoupper(sanitizeText($_POST['job_title_name'] ?? '', 100));
        if ($name === '') $error = 'Job title is required.';
        else { $s=$conn->prepare("INSERT INTO user_job_titles(name,status) VALUES(?,'ACTIVE') ON DUPLICATE KEY UPDATE status='ACTIVE'");$s->bind_param('s',$name);$s->execute();$success='Job title added successfully.'; }
    } elseif ($action === 'add_role') {
        $code=strtoupper(preg_replace('/[^A-Z0-9]+/','_',trim((string)($_POST['role_code']??''))));$name=sanitizeText($_POST['role_name']??'',100);$template=sanitizeText($_POST['template_role']??'',50);
        if($code===''||$name===''||!isset($roleOptions[$template]))$error='Role code, name, and permission template are required.';
        else{$conn->begin_transaction();try{$s=$conn->prepare("INSERT INTO user_roles(code,name,status) VALUES(?,?,'ACTIVE') ON DUPLICATE KEY UPDATE name=VALUES(name),status='ACTIVE'");$s->bind_param('ss',$code,$name);$s->execute();$s=$conn->prepare("INSERT INTO role_permissions(role,module_name,can_view,can_create,can_edit,can_delete,can_approve,can_print) SELECT ?,module_name,can_view,can_create,can_edit,can_delete,can_approve,can_print FROM role_permissions WHERE role=? ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_create=VALUES(can_create),can_edit=VALUES(can_edit),can_delete=VALUES(can_delete),can_approve=VALUES(can_approve),can_print=VALUES(can_print)");$s->bind_param('ss',$code,$template);$s->execute();$conn->commit();$success='Role added with copied permissions.';}catch(Throwable $e){$conn->rollback();$error='Unable to add role.';}}

    } elseif ($action === 'create_user') {
        $username = sanitizeText($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = sanitizeText($_POST['email'] ?? '', 100);
        $employeeId = strtoupper(sanitizeText($_POST['employee_id'] ?? '', 50));
        $firstName = sanitizeText($_POST['first_name'] ?? '', 50);
        $middleName = sanitizeText($_POST['middle_name'] ?? '', 50);
        $lastName = sanitizeText($_POST['last_name'] ?? '', 50);
        $suffix = sanitizeText($_POST['suffix'] ?? '', 20);
        $position = sanitizeText($_POST['position'] ?? '', 100);
        $employmentStatus = strtoupper(sanitizeText($_POST['employment_status'] ?? '', 30));
        $dateHired = trim((string) ($_POST['date_hired'] ?? ''));
        $phone = sanitizeText($_POST['phone'] ?? '', 20);
        $role = sanitizeText($_POST['role'] ?? '', 50);
        $department = sanitizeText($_POST['department'] ?? '', 50);

        if ($employeeId === '' || $username === '' || $password === '' || $confirmPassword === '' || $firstName === '' || $lastName === '' || $position === '' || $employmentStatus === '' || $dateHired === '' || $role === '' || (!in_array(canonicalRole($role), $rolesWithoutDepartment, true) && $department === '')) {
            $error = 'All account creation fields are required.';
        } elseif (!in_array($employmentStatus, ['PERMANENT','CONTRACT OF SERVICE'], true)) {
            $error = 'Please select a valid employment status.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateHired) || $dateHired > date('Y-m-d')) {
            $error = 'Please enter a valid date hired that is not in the future.';
        } elseif (!isset($staffRoleOptions[$role])) {
            $error = 'Please select a valid role.';
        } elseif (!isset($jobTitleOptions[$position])) {
            $error = 'Please select a valid job title.';
        } elseif ($department !== '' && !isset($departmentOptions[$department])) {
            $error = 'Please select a valid department.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $error = 'Username may contain letters, numbers, dots, dashes, and underscores only.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif ($policyError = passwordPolicyError($password, $username, $email)) {
            $error = $policyError;
        } else {
            $existingStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR employee_id = ? OR (? <> '' AND email = ?)");
            $existingStmt->bind_param("ssss", $username, $employeeId, $email, $email);
            $existingStmt->execute();
            $existingNameStmt = $conn->prepare("SELECT user_id FROM users WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(?)) AND LOWER(TRIM(last_name)) = LOWER(TRIM(?)) LIMIT 1");
            $existingNameStmt->bind_param('ss', $firstName, $lastName);
            $existingNameStmt->execute();

            if ($existingNameStmt->get_result()->num_rows > 0) {
                $error = 'A user with this first name and last name already exists. Edit the existing account instead.';
            } elseif ($existingStmt->get_result()->num_rows > 0) {
                $error = 'Employee ID, username, or email already exists.';
            } else {
                $hashedPassword = hashPassword($password);
                $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $suffix], fn($value) => $value !== '')));
                $createStmt = $conn->prepare("
                    INSERT INTO users (employee_id,username,password,email,first_name,middle_name,last_name,suffix,full_name,position,employment_status,date_hired,phone,role,department,status,is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ACTIVE',TRUE)
                ");
                $departmentForSave = $department === '' ? null : $department;
                $emailForSave = $email === '' ? null : $email;
                $middleNameForSave=$middleName===''?null:$middleName;$suffixForSave=$suffix===''?null:$suffix;$phoneForSave=$phone===''?null:$phone;
                $createStmt->bind_param("sssssssssssssss", $employeeId,$username,$hashedPassword,$emailForSave,$firstName,$middleNameForSave,$lastName,$suffixForSave,$fullName,$position,$employmentStatus,$dateHired,$phoneForSave,$role,$departmentForSave);
                if ($createStmt->execute()) {
                    $newUserId = $conn->insert_id;
                    $conn->query("UPDATE users SET id = user_id WHERE user_id = " . (int) $newUserId . " AND id IS NULL");
                    logSecurityEvent($conn, 'Create User', 'Created user account: ' . $username);
                    $success = 'Staff account created successfully.';
                } else {
                    $error = 'Failed to create staff account.';
                }
            }
        }
    } elseif ($action === 'delete_user') {
        if ($userId <= 0) {
            $error = 'Invalid user selected.';
        } elseif ($userId === intval($_SESSION['user_id'])) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = 'INACTIVE', is_active = FALSE WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logSecurityEvent($conn, 'Deactivate User', 'Deactivated user ID ' . $userId);
                $success = 'User account deactivated successfully.';
            } else {
                $error = 'Failed to deactivate user account.';
            }
        }
    } elseif ($action === 'update_user') {
        $role = sanitizeText($_POST['role'] ?? '', 50);
        $department = sanitizeText($_POST['department'] ?? '', 50);
        $status = sanitizeText($_POST['status'] ?? 'ACTIVE', 20);
        $isActive = $status === 'ACTIVE' ? 1 : 0;
        $newPassword = $_POST['new_password'] ?? '';
        $position = isset($_POST['position']) ? sanitizeText($_POST['position'], 100) : null;
        $allowedStatuses = ['ACTIVE', 'INACTIVE', 'RESIGNED'];
        if ($userId <= 0) {
            $error = 'Invalid user selected.';
        } elseif (!isset($roleOptions[$role])) {
            $error = 'Please select a valid role.';
        } elseif (!in_array(canonicalRole($role), $rolesWithoutDepartment, true) && $department === '') {
            $error = 'Please select a department for this role.';
        } elseif ($department !== '' && $department !== null && !isset($departmentOptions[$department])) {
            $error = 'Please select a valid department.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $error = 'Please select a valid status.';
        } elseif ($userId === intval($_SESSION['user_id']) && ($role !== ($_SESSION['role'] ?? '') || $department !== ($_SESSION['department'] ?? ''))) {
            $error = 'You cannot change your own role or department.';
        } elseif ($userId === intval($_SESSION['user_id']) && !$isActive) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $department = $department === '' ? null : $department;
            $beforeStmt = $conn->prepare("SELECT username, email, role, department, position FROM users WHERE user_id = ?");
            $beforeStmt->bind_param("i", $userId);
            $beforeStmt->execute();
            $before = $beforeStmt->get_result()->fetch_assoc() ?: [];
            $position = $position ?? (string) ($before['position'] ?? '');
            if ($position !== '' && !isset($jobTitleOptions[$position]) && $position !== ($before['position'] ?? '')) {
                $error = 'Please select a valid position / job title.';
            }
            if ($newPassword !== '' && ($policyError = passwordPolicyError($newPassword, $before['username'] ?? '', $before['email'] ?? ''))) {
                $error = $policyError;
            }

            if (!$error) {
                $stmt = $conn->prepare("UPDATE users SET role = ?, department = ?, position = ?, status = ?, is_active = ? WHERE user_id = ?");
                $stmt->bind_param("ssssii", $role, $department, $position, $status, $isActive, $userId);

                if ($stmt->execute()) {
                    if ($newPassword !== '') {
                        $hashedPassword = hashPassword($newPassword);
                        $passwordStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $passwordStmt->bind_param("si", $hashedPassword, $userId);
                        if (!$passwordStmt->execute()) {
                            $error = 'User was updated, but password reset failed.';
                        } else {
                            $resolverId = (int) ($_SESSION['user_id'] ?? 0);
                            $resolveStmt = $conn->prepare("UPDATE password_reset_requests SET status='RESOLVED',resolved_at=NOW(),resolved_by=? WHERE user_id=? AND status='PENDING'");
                            $resolveStmt->bind_param('ii', $resolverId, $userId);
                            $resolveStmt->execute();
                        }
                    }

                    if (!$error) {
                        logSecurityEvent($conn, 'Update User', 'Updated user ID ' . $userId);
                        if ((string) ($before['position'] ?? '') !== $position) {
                            logSecurityEvent($conn, 'Change Position', 'Changed user ID ' . $userId . ' from ' . ($before['position'] ?? '') . ' to ' . $position);
                        }
                        if (($before['role'] ?? '') !== $role) {
                            logSecurityEvent($conn, 'Change Role', 'Changed user ID ' . $userId . ' from ' . ($before['role'] ?? '') . ' to ' . $role);
                        }
                        if (($before['department'] ?? '') !== $department) {
                            logSecurityEvent($conn, 'Change Department', 'Changed user ID ' . $userId . ' from ' . ($before['department'] ?? '') . ' to ' . ($department ?? ''));
                        }
                        $success = 'User updated successfully.';
                    }
                } else {
                    $error = 'Failed to update user.';
                }
            }
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('admin_users.php'.(($_GET['workspace']??'')==='cssr'?'?workspace=cssr':''));
}

$users = $conn->query("
    SELECT user_id, employee_id, username, email, first_name, middle_name, last_name, suffix, position, employment_status, date_hired, phone, role, department, status, is_active, created_at, updated_at
    FROM users
    ORDER BY role = 'SUPER_ADMIN' DESC, last_name, first_name, username
")->fetch_all(MYSQLI_ASSOC);
$passwordResetRequests = $conn->query("SELECT pr.request_id,pr.requested_at,u.user_id,u.employee_id,u.username,u.first_name,u.last_name,u.position FROM password_reset_requests pr JOIN users u ON u.user_id=pr.user_id WHERE pr.status='PENDING' ORDER BY pr.requested_at ASC")->fetch_all(MYSQLI_ASSOC);
$activeUserCount = count(array_filter($users, static fn(array $user): bool => ($user['status'] ?? '') === 'ACTIVE'));
$inactiveUserCount = count($users) - $activeUserCount;
$departmentCount = count($departmentOptions);
$roleCount = count($roleOptions);
$jobTitleCount = count($jobTitleOptions);
$cssrWorkspace=($_GET['workspace']??'')==='cssr';
?>
<?php if($cssrWorkspace): require_once __DIR__.'/../CSSR_INVENTORY/includes/external-workspace.php'; cssrExternalStart('User Management','users','admin-users-page',['/Nursing_System/public/css/style.css','/Nursing_System/public/css/dashboard.css','/Nursing_System/public/css/admin-users-layout.css?v=1','/Nursing_System/public/css/admin-users.css?v=4','/Nursing_System/public/vendor/bootstrap-icons/font/bootstrap-icons.min.css']); else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css?v=20260916-1">
    <link rel="stylesheet" href="../public/css/admin-users-layout.css?v=1">

<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script>
<link rel="stylesheet" href="../public/css/admin-users.css?v=4">
<link rel="stylesheet" href="../public/vendor/bootstrap-icons/font/bootstrap-icons.min.css"></head>
<body class="module-ui admin-users-page standalone-users-page">
    <main class="standalone-user-shell">
        <header class="standalone-user-header">
            <div class="standalone-user-brand">
                <img src="../public/images/branding/jvgh_seal.png" alt="Joni Villanueva General Hospital logo">
                <div>
                    <span>Joni Villanueva General Hospital</span>
                    <strong>System User Management</strong>
                </div>
            </div>
            <div class="standalone-user-actions">
                <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
                <a href="system_menu.php" class="btn btn-info">Menu</a>
                <a href="../api/logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>
<?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <section class="admin-panel admin-hero">
                <div class="admin-toolbar">
                    <div>
                        <h2>User Management</h2>
                    </div>
                    <button type="button" class="btn btn-primary" id="open-create-user"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</button>
                </div>
            </section>

            <section class="admin-metrics" aria-label="User management summary">
                <div class="admin-metric"><span>Total accounts</span><strong><?php echo count($users); ?></strong></div>
                <div class="admin-metric"><span>Active accounts</span><strong><?php echo $activeUserCount; ?></strong></div>
                <div class="admin-metric"><span>Inactive / resigned</span><strong><?php echo $inactiveUserCount; ?></strong></div>
                <div class="admin-metric"><span>Active roles</span><strong><?php echo $roleCount; ?></strong></div>
                <div class="admin-metric"><span>Pending resets</span><strong><?php echo count($passwordResetRequests); ?></strong></div>
            </section>

            <section class="admin-panel account-setup" aria-labelledby="account-setup-title">
                <div class="account-setup-heading">
                    <h3 id="account-setup-title">Account Setup</h3>
                    <span class="user-meta"><?php echo $departmentCount; ?> departments &middot; <?php echo $roleCount; ?> roles &middot; <?php echo $jobTitleCount; ?> job titles</span>
                </div>
                <div class="setup-launch-grid">
                    <button type="button" class="setup-launch" data-open-setup="department-entry-modal"><i class="bi bi-building" aria-hidden="true"></i> Add Department</button>
                    <button type="button" class="setup-launch" data-open-setup="role-entry-modal"><i class="bi bi-shield-check" aria-hidden="true"></i> Add Role</button>
                    <button type="button" class="setup-launch" data-open-setup="job-title-entry-modal"><i class="bi bi-person-badge" aria-hidden="true"></i> Add Job Title</button>
                </div>
                <div class="setup-entry-modal" id="department-entry-modal" aria-hidden="true"><div class="setup-entry-card"><div class="create-user-dialog-head"><h3>Add Department</h3><button type="button" class="create-user-close" data-close-setup aria-label="Close">&times;</button></div>
                    <form method="POST" class="form-section add-department-form">
                        <input type="hidden" name="action" value="add_department">
                        <h4>Add Department</h4>
                        <div class="form-group"><label>Department Code</label><input type="text" name="department_code" required placeholder="e.g. PHARMACY"></div>
                        <div class="form-group"><label>Department Name</label><input type="text" name="department_name" required placeholder="e.g. Pharmacy Department"></div>
                        <button class="btn btn-primary" type="submit">Add Department</button>
                    </form>
                </div></div>
                <div class="setup-entry-modal" id="role-entry-modal" aria-hidden="true"><div class="setup-entry-card"><div class="create-user-dialog-head"><h3>Add Role</h3><button type="button" class="create-user-close" data-close-setup aria-label="Close">&times;</button></div>
                    <form method="POST" class="form-section add-role-form">
                        <input type="hidden" name="action" value="add_role">
                        <h4>Add Role</h4>
                        <div class="form-group"><label>Role Code</label><input type="text" name="role_code" required placeholder="e.g. PHARMACY_STAFF"></div>
                        <div class="form-group"><label>Role Name</label><input type="text" name="role_name" required placeholder="e.g. Pharmacy Staff"></div>
                        <div class="form-group"><label>Copy Permissions From</label><select name="template_role" required><option value="">Select permission template</option><?php foreach($roleOptions as $value=>$label):?><option value="<?=htmlspecialchars($value)?>"><?=htmlspecialchars($label)?></option><?php endforeach;?></select></div>
                        <button class="btn btn-primary" type="submit">Add Role</button>
                    </form>
                </div></div>
                <div class="setup-entry-modal" id="job-title-entry-modal" aria-hidden="true"><div class="setup-entry-card"><div class="create-user-dialog-head"><h3>Add Job Title</h3><button type="button" class="create-user-close" data-close-setup aria-label="Close">&times;</button></div>
                    <form method="POST" class="form-section add-job-title-form">
                        <input type="hidden" name="action" value="add_job_title">
                        <h4>Add Job Title</h4>
                        <div class="form-group"><label>Job Title</label><input type="text" name="job_title_name" required maxlength="100" placeholder="e.g. Nurse III"></div>
                        <button class="btn btn-primary" type="submit">Add Job Title</button>
                    </form>
                </div></div>
            </section>

            <?php if ($passwordResetRequests): ?>
            <section class="admin-panel" style="border-left:4px solid #f4b860!important;">
                <h3>Password Reset Requests <span style="display:inline-grid;place-items:center;min-width:25px;height:25px;margin-left:6px;border-radius:999px;background:#fff1d6;color:#8a5900;font-size:12px;"><?php echo count($passwordResetRequests); ?></span></h3>
                <p class="password-note">Enter a temporary password for the requested account, then select Save. The request will close automatically.</p>
                <div class="table-responsive"><table class="table"><thead><tr><th>Employee</th><th>Username</th><th>Requested</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($passwordResetRequests as $request): ?><tr><td><strong><?php echo htmlspecialchars(trim($request['first_name'].' '.$request['last_name'])); ?></strong><span class="user-meta"><?php echo htmlspecialchars($request['employee_id'] ?: 'No employee ID'); ?> · <?php echo htmlspecialchars($request['position'] ?: 'No position'); ?></span></td><td><?php echo htmlspecialchars($request['username']); ?></td><td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($request['requested_at']))); ?></td><td><a class="btn btn-secondary reset-request-view" href="#user-<?php echo (int) $request['user_id']; ?>" data-reset-user="<?php echo (int) $request['user_id']; ?>">View</a></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
            <?php endif; ?>

            <div class="create-user-modal" id="create-user-modal" aria-hidden="true">
            <section class="admin-panel create-user-dialog" role="dialog" aria-modal="true" aria-labelledby="create-user-title">
                <div class="create-user-dialog-head"><h3 id="create-user-title">Create Staff Account</h3><button type="button" class="create-user-close" id="close-create-user" aria-label="Close">&times;</button></div>
                <form method="POST" class="create-user-form" autocomplete="off">
                    <input type="hidden" name="action" value="create_user">
                    <section class="account-form-section">
                        <h4>Employee Identity</h4>
                        <p>Basic identifying and contact information.</p>
                        <div class="create-account-grid">
                        <div class="form-group">
                            <label for="new-employee-id">Employee ID</label>
                            <input type="text" id="new-employee-id" name="employee_id" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-first-name">First Name</label>
                            <input type="text" id="new-first-name" name="first_name" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-middle-name">Middle Name <small>(Optional)</small></label>
                            <input type="text" id="new-middle-name" name="middle_name" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-last-name">Last Name</label>
                            <input type="text" id="new-last-name" name="last_name" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-suffix">Suffix <small>(Optional)</small></label>
                            <input type="text" id="new-suffix" name="suffix" placeholder="Jr., Sr., III" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-phone">Contact Number <small>(Optional)</small></label>
                            <input type="tel" id="new-phone" name="phone" autocomplete="off">
                        </div>
                        </div>
                    </section>
                    <section class="account-form-section">
                        <h4>Employment Details</h4>
                        <p>Job assignment and employment information.</p>
                        <div class="create-account-grid">
                        <div class="form-group">
                            <label for="new-position">Position / Job Title</label>
                            <select id="new-position" name="position" required>
                                <option value="">Select job title</option>
                                <?php foreach ($jobTitleOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars(strtoupper($label)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new-employment-status">Employment Status</label>
                            <select id="new-employment-status" name="employment_status" required><option value="">Select status</option><option value="PERMANENT">Permanent</option><option value="CONTRACT OF SERVICE">Contract of Service</option></select>
                        </div>
                        <div class="form-group">
                            <label for="new-date-hired">Date Hired</label>
                            <input type="date" id="new-date-hired" name="date_hired" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="new-department">Department</label>
                            <select id="new-department" name="department">
                                <option value="">None</option>
                                <?php foreach ($departmentOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                    </section>
                    <section class="account-form-section">
                        <h4>Account Access</h4>
                        <p>Login credentials and system access assignment.</p>
                        <div class="create-account-grid">
                        <div class="form-group">
                            <label for="new-username">Username</label>
                            <input type="text" id="new-username" name="username" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-email">Email <small>(Optional)</small></label>
                            <input type="email" id="new-email" name="email" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="new-password">Password</label>
                            <div class="password-field"><input type="password" id="new-password" name="password" minlength="10" required autocomplete="new-password"><button type="button" class="password-view" data-password-view="new-password" aria-label="Show password">&#128065;</button></div>
                        </div>
                        <div class="form-group">
                            <label for="new-confirm-password">Confirm Password</label>
                            <div class="password-field"><input type="password" id="new-confirm-password" name="confirm_password" minlength="10" required autocomplete="new-password"><button type="button" class="password-view" data-password-view="new-confirm-password" aria-label="Show password">&#128065;</button></div>
                        </div>
                        <div class="form-group">
                            <label for="new-role">Role</label>
                            <select id="new-role" name="role" required>
                                <?php foreach ($staffRoleOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                    </section>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
            </section>
            </div>

            <section class="admin-panel user-directory">
                <div class="directory-heading"><h3>Staff Accounts</h3><span id="user-result-count" role="status" aria-live="polite"></span></div>
                <div class="directory-filters">
                    <div class="form-group"><label for="user-search">Search accounts</label><input id="user-search" type="search" placeholder="Name, username, employee ID, email, position"></div>
                    <div class="form-group"><label for="filter-role">Role</label><select id="filter-role"><option value="">All roles</option><?php foreach ($roleOptions as $code => $label): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="filter-department">Department</label><select id="filter-department"><option value="">All departments</option><option value="__none">Unassigned</option><?php foreach ($departmentOptions as $code => $label): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="filter-status">Status</label><select id="filter-status"><option value="">All statuses</option><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="RESIGNED">Resigned</option></select></div>
                    <button type="button" class="btn btn-secondary directory-clear" id="clear-user-filters" title="Clear filters" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                </div>
                <div class="table-responsive">
                    <table class="table account-directory-table">
                        <thead>
                            <tr>
                                <th scope="col">Employee / Account</th>
                                <th scope="col">Position</th>
                                <th scope="col">Role</th>
                                <th scope="col">Department</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr id="user-<?php echo (int) $user['user_id']; ?>" class="user-account-row" data-role="<?= htmlspecialchars($user['role']) ?>" data-department="<?= htmlspecialchars($user['department'] ?: '__none') ?>" data-status="<?= htmlspecialchars($user['status'] ?? ($user['is_active'] ? 'ACTIVE' : 'INACTIVE')) ?>" data-search="<?= htmlspecialchars(implode(' ', [$user['first_name'], $user['last_name'], $user['username'], $user['employee_id'], $user['email'], $user['position']])) ?>">
                                    <td>
                                        <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                        <span class="user-meta">Username: <?php echo htmlspecialchars($user['username']); ?></span>
                                        <span class="user-meta">Employee ID: <?php echo htmlspecialchars($user['employee_id'] ?: 'N/A'); ?></span>
                                        <span class="user-meta account-detail">Email: <?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                                        <span class="user-meta">Employment: <?php echo htmlspecialchars($user['employment_status'] ?: 'N/A'); ?><?php echo $user['date_hired'] ? ' · Hired ' . htmlspecialchars($user['date_hired']) : ''; ?></span>
                                        <?php if (!empty($user['phone'])): ?><span class="user-meta">Contact: <?php echo htmlspecialchars($user['phone']); ?></span><?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(strtoupper($user['position'] ?: 'Unassigned')) ?></td>
                                    <td><?= htmlspecialchars($roleOptions[$user['role']] ?? roleLabel($user['role'])) ?></td>
                                    <td><?= htmlspecialchars($departmentOptions[$user['department']] ?? ($user['department'] ?: 'Unassigned')) ?></td>
                                    <td><span class="account-status status-<?= strtolower(htmlspecialchars($user['status'] ?? ($user['is_active'] ? 'ACTIVE' : 'INACTIVE'))) ?>"><?= htmlspecialchars(ucfirst(strtolower($user['status'] ?? ($user['is_active'] ? 'ACTIVE' : 'INACTIVE')))) ?></span></td>
                                    <td>
                                        <button type="button" class="btn btn-secondary edit-user-button" data-edit-user="<?= (int) $user['user_id'] ?>" aria-label="Edit <?= htmlspecialchars($user['username']) ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit</button>
                                        <div class="user-form-grid" hidden>
                                            <form method="POST" class="user-update-form update-user-form">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

                                            <div class="form-group">
                                                <label for="position-<?= (int) $user['user_id'] ?>">Position / Job Title</label>
                                                <select id="position-<?= (int) $user['user_id'] ?>" name="position">
                                                    <option value="">Unassigned</option>
                                                    <?php if (!empty($user['position']) && !isset($jobTitleOptions[$user['position']])): ?>
                                                        <option value="<?= htmlspecialchars($user['position']) ?>" selected><?= htmlspecialchars(strtoupper($user['position'])) ?></option>
                                                    <?php endif; ?>
                                                    <?php foreach ($jobTitleOptions as $positionValue => $positionLabel): ?>
                                                        <option value="<?= htmlspecialchars($positionValue) ?>" <?= $user['position'] === $positionValue ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper($positionLabel)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="role-<?php echo htmlspecialchars($user['user_id']); ?>">Role</label>
                                                <select id="role-<?php echo htmlspecialchars($user['user_id']); ?>" name="role">
                                                    <?php foreach ($roleOptions as $roleValue => $roleLabel): ?>
                                                        <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo $user['role'] === $roleValue ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($roleLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="department-<?php echo htmlspecialchars($user['user_id']); ?>">Department</label>
                                                <select id="department-<?php echo htmlspecialchars($user['user_id']); ?>" name="department">
                                                    <option value="">None</option>
                                                    <?php foreach ($departmentOptions as $departmentValue => $departmentLabel): ?>
                                                        <option value="<?php echo htmlspecialchars($departmentValue); ?>" <?php echo $user['department'] === $departmentValue ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($departmentLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="password-<?php echo htmlspecialchars($user['user_id']); ?>">Temporary Password</label>
                                                <div class="password-field"><input type="password" id="password-<?php echo htmlspecialchars($user['user_id']); ?>" name="new_password" minlength="10" placeholder="Enter temporary password"><button type="button" class="password-view" data-password-view="password-<?php echo htmlspecialchars($user['user_id']); ?>" aria-label="Show password">&#128065;</button></div>
                                            </div>

                                            <div class="form-group">
                                                <label for="status-<?php echo htmlspecialchars($user['user_id']); ?>">Status</label>
                                                <select id="status-<?php echo htmlspecialchars($user['user_id']); ?>" name="status">
                                                    <?php foreach (['ACTIVE', 'INACTIVE', 'RESIGNED'] as $statusOption): ?>
                                                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($user['status'] ?? ($user['is_active'] ? 'ACTIVE' : 'INACTIVE')) === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($statusOption); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label>Updated</label>
                                                <span class="user-meta"><?php echo htmlspecialchars($user['updated_at'] ?? 'N/A'); ?></span>
                                            </div>

                                            <button type="submit" class="btn btn-primary">Save</button>
                                            </form>
                                            <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?>
                                                <form method="POST" class="inline-action-form delete-user-form">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <button type="submit" class="btn btn-danger">Deactivate</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="user-empty-state" hidden><td colspan="6">No accounts match your search.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="directory-pagination"><span id="user-page-summary"></span><div class="directory-page-actions"><label for="user-page-size">Rows</label><select id="user-page-size"><option value="15">15</option><option value="30">30</option><option value="50">50</option></select><button type="button" class="btn btn-secondary" id="users-prev" title="Previous page" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><button type="button" class="btn btn-secondary" id="users-next" title="Next page" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div></div>
            </section>

<?php if(!$cssrWorkspace): ?>    </main><?php endif; ?>
    <div id="save-user-modal" class="confirm-modal" aria-hidden="true">
        <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="save-user-title">
            <h3 id="save-user-title">Save Account Changes</h3>
            <p class="compact-meta" id="save-user-message">Are you sure you want to save these account changes?</p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" id="cancel-save-user">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-save-user">Save</button>
            </div>
        </div>
    </div>
    <div id="add-setup-modal" class="confirm-modal" aria-hidden="true">
        <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="add-setup-title">
            <h3 id="add-setup-title">Confirm Addition</h3>
            <p class="compact-meta" id="add-setup-message">Are you sure you want to add this record?</p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" id="cancel-add-setup">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-add-setup">Add</button>
            </div>
        </div>
    </div>
    <div id="delete-user-modal" class="confirm-modal" aria-hidden="true">
        <div class="confirm-card" role="dialog" aria-modal="true">
            <h3>Deactivate Account</h3>
            <p class="compact-meta">Are you sure you want to deactivate this user account? The account will remain in the audit history.</p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" id="cancel-delete-user">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-user">Deactivate</button>
            </div>
        </div>
    </div>
    <script>
        const deleteUserModal = document.getElementById('delete-user-modal');
        const cancelDeleteUser = document.getElementById('cancel-delete-user');
        const confirmDeleteUser = document.getElementById('confirm-delete-user');
        const saveUserModal = document.getElementById('save-user-modal');
        const cancelSaveUser = document.getElementById('cancel-save-user');
        const confirmSaveUser = document.getElementById('confirm-save-user');
        const saveUserTitle = document.getElementById('save-user-title');
        const saveUserMessage = document.getElementById('save-user-message');
        const addSetupModal = document.getElementById('add-setup-modal');
        const addSetupTitle = document.getElementById('add-setup-title');
        const addSetupMessage = document.getElementById('add-setup-message');
        const cancelAddSetup = document.getElementById('cancel-add-setup');
        const confirmAddSetup = document.getElementById('confirm-add-setup');
        let pendingDeleteForm = null;
        let pendingSaveForm = null;
        let pendingSetupForm = null;
        const createUserModal = document.getElementById('create-user-modal');
        const openCreateUser = document.getElementById('open-create-user');
        const closeCreateUser = document.getElementById('close-create-user');

        function setCreateUserModal(open) {
            createUserModal?.classList.toggle('open', open);
            createUserModal?.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.style.overflow = open ? 'hidden' : '';
        }
        openCreateUser?.addEventListener('click', () => setCreateUserModal(true));
        closeCreateUser?.addEventListener('click', () => setCreateUserModal(false));
        createUserModal?.addEventListener('click', event => { if (event.target === createUserModal) setCreateUserModal(false); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && createUserModal?.classList.contains('open')) setCreateUserModal(false); });

        document.querySelectorAll('[data-password-view]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordView);
                if (!input) return;
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.innerHTML = showing ? '&#128065;' : '&#128065;&#xFE0E;';
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });

        document.querySelectorAll('[data-open-setup]').forEach(button => {
            button.addEventListener('click', () => {
                const modal = document.getElementById(button.dataset.openSetup);
                modal?.classList.add('open');
                modal?.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });
        });

        const accountRows = Array.from(document.querySelectorAll('.user-account-row'));
        const searchInput = document.getElementById('user-search');
        const roleFilter = document.getElementById('filter-role');
        const departmentFilter = document.getElementById('filter-department');
        const statusFilter = document.getElementById('filter-status');
        const pageSizeInput = document.getElementById('user-page-size');
        const previousPage = document.getElementById('users-prev');
        const nextPage = document.getElementById('users-next');
        let accountPage = 1;
        function renderAccounts() {
            const query = searchInput.value.trim().toLowerCase();
            const matchingAccounts = accountRows.filter(row =>
                (!query || row.dataset.search.toLowerCase().includes(query)) &&
                (!roleFilter.value || row.dataset.role === roleFilter.value) &&
                (!departmentFilter.value || row.dataset.department === departmentFilter.value) &&
                (!statusFilter.value || row.dataset.status === statusFilter.value)
            );
            const pageSize = Number(pageSizeInput.value);
            const pages = Math.max(1, Math.ceil(matchingAccounts.length / pageSize));
            accountPage = Math.min(accountPage, pages);
            const start = (accountPage - 1) * pageSize;
            const visibleRows = new Set(matchingAccounts.slice(start, start + pageSize));
            accountRows.forEach(row => { row.hidden = !visibleRows.has(row); });
            document.getElementById('user-empty-state').hidden = matchingAccounts.length > 0;
            document.getElementById('user-result-count').textContent = `${matchingAccounts.length} of ${accountRows.length} accounts`;
            document.getElementById('user-page-summary').textContent = matchingAccounts.length
                ? `${start + 1}-${Math.min(start + pageSize, matchingAccounts.length)} of ${matchingAccounts.length}`
                : '0 accounts';
            previousPage.disabled = accountPage === 1;
            nextPage.disabled = accountPage === pages;
        }
        [searchInput, roleFilter, departmentFilter, statusFilter, pageSizeInput].forEach(control => {
            control.addEventListener(control === searchInput ? 'input' : 'change', () => { accountPage = 1; renderAccounts(); });
        });
        document.getElementById('clear-user-filters').addEventListener('click', () => {
            [searchInput, roleFilter, departmentFilter, statusFilter].forEach(control => { control.value = ''; });
            accountPage = 1;
            renderAccounts();
        });
        previousPage.addEventListener('click', () => { accountPage--; renderAccounts(); });
        nextPage.addEventListener('click', () => { accountPage++; renderAccounts(); });
        renderAccounts();

        let closeAccountEditor = null;
        document.querySelectorAll('[data-reset-user], [data-edit-user]').forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                closeAccountEditor?.();
                const userId = button.dataset.resetUser || button.dataset.editUser;
                const row = document.getElementById(`user-${userId}`);
                const controls = row?.querySelector('.user-form-grid');
                const password = document.getElementById(`password-${userId}`);
                if (!controls) return;
                const originalParent = controls.parentNode;
                const restoreMarker = document.createComment(`user-controls-${userId}`);
                originalParent.insertBefore(restoreMarker, controls);
                document.body.appendChild(controls);
                controls.hidden = false;
                controls.setAttribute('role', 'dialog');
                controls.setAttribute('aria-modal', 'true');
                controls.setAttribute('aria-label', 'Edit user account');
                let closeButton = controls.querySelector('.user-edit-popup-close');
                if (!closeButton) {
                    closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'user-edit-popup-close';
                    closeButton.setAttribute('aria-label', 'Close user form');
                    closeButton.innerHTML = '&times;';
                    controls.append(closeButton);
                }
                let accountSummary = controls.querySelector('.user-edit-account-summary');
                if (!accountSummary) {
                    accountSummary = document.createElement('div');
                    accountSummary.className = 'user-edit-account-summary';
                    controls.prepend(accountSummary);
                }
                const accountName = row.querySelector('.user-name')?.textContent?.trim() || 'User Account';
                const accountDetails = Array.from(row.querySelectorAll('td:first-child .user-meta'))
                    .slice(0, 4)
                    .map(item => item.textContent.trim());
                accountSummary.replaceChildren();
                const nameLabel = document.createElement('strong');
                nameLabel.textContent = accountName;
                const detailLabel = document.createElement('span');
                detailLabel.textContent = accountDetails.join(' | ');
                accountSummary.append(nameLabel, detailLabel);
                const closePopup = () => {
                    controls.classList.remove('user-edit-popup');
                    controls.hidden = true;
                    controls.removeAttribute('role');
                    controls.removeAttribute('aria-modal');
                    document.body.classList.remove('user-edit-open');
                    restoreMarker.parentNode?.insertBefore(controls, restoreMarker);
                    restoreMarker.remove();
                    closeAccountEditor = null;
                    button.focus();
                };
                closeAccountEditor = closePopup;
                closeButton.onclick = closePopup;
                controls.classList.add('user-edit-popup');
                document.body.classList.add('user-edit-open');
                if (button.dataset.resetUser) password?.focus();
                else controls.querySelector('select')?.focus();
            });
        });
        document.addEventListener('keydown', event => {
            if (!closeAccountEditor || document.querySelector('.confirm-modal.open')) return;
            if (event.key === 'Escape') closeAccountEditor();
            if (event.key === 'Tab') {
                const dialog = document.querySelector('.user-edit-popup');
                const focusable = Array.from(dialog.querySelectorAll('button, input:not([type="hidden"]), select')).filter(control => !control.disabled);
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        document.querySelectorAll('.setup-entry-modal').forEach(modal => {
            const close = () => { modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''; };
            modal.querySelector('[data-close-setup]')?.addEventListener('click', close);
            modal.addEventListener('click', event => { if (event.target === modal) close(); });
        });

        const rolesWithoutDepartment = new Set([
            'SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE',
            'SUPERVISOR', 'ASSISTANT_CHIEF_NURSE'
        ]);

        function syncDepartmentWithRole(form) {
            const roleSelect = form.querySelector('[name="role"]');
            const departmentSelect = form.querySelector('[name="department"]');
            if (!roleSelect || !departmentSelect) return;

            const departmentIsOptional = rolesWithoutDepartment.has(roleSelect.value);
            departmentSelect.disabled = false;
            departmentSelect.required = !departmentIsOptional;
            departmentSelect.closest('.form-group')?.classList.remove('department-not-applicable');
        }

        document.querySelectorAll('.create-user-form, .update-user-form').forEach((form) => {
            const roleSelect = form.querySelector('[name="role"]');
            syncDepartmentWithRole(form);
            roleSelect?.addEventListener('change', () => syncDepartmentWithRole(form));
        });

        document.querySelectorAll('.add-department-form, .add-role-form, .add-job-title-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingSetupForm = form;
                const isRole = form.classList.contains('add-role-form');
                const isJobTitle = form.classList.contains('add-job-title-form');
                const nameField = form.querySelector(isRole ? '[name="role_name"]' : (isJobTitle ? '[name="job_title_name"]' : '[name="department_name"]'));
                const codeField = form.querySelector(isRole ? '[name="role_code"]' : '[name="department_code"]');
                const recordType = isRole ? 'role' : (isJobTitle ? 'job title' : 'department');
                const label = isRole ? 'Role' : (isJobTitle ? 'Job Title' : 'Department');
                addSetupTitle.textContent = `Add ${label}`;
                addSetupMessage.textContent = isJobTitle
                    ? `Add the job title “${nameField.value.trim()}”?`
                    : `Add the ${recordType} “${nameField.value.trim()}” with code “${codeField.value.trim().toUpperCase()}”?`;
                confirmAddSetup.textContent = `Add ${label}`;
                addSetupModal.classList.add('open');
                addSetupModal.setAttribute('aria-hidden', 'false');
            });
        });

        function closeAddSetupModal() {
            addSetupModal.classList.remove('open');
            addSetupModal.setAttribute('aria-hidden', 'true');
            pendingSetupForm = null;
        }

        cancelAddSetup.addEventListener('click', closeAddSetupModal);
        addSetupModal.addEventListener('click', (event) => {
            if (event.target === addSetupModal) closeAddSetupModal();
        });
        confirmAddSetup.addEventListener('click', () => {
            if (pendingSetupForm) pendingSetupForm.submit();
        });

        document.querySelectorAll('.update-user-form').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
            });
        });

        document.querySelector('.create-user-form')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            pendingSaveForm = form;
            const firstName = form.querySelector('[name="first_name"]').value.trim();
            const lastName = form.querySelector('[name="last_name"]').value.trim();
            const username = form.querySelector('[name="username"]').value.trim();
            const roleSelect = form.querySelector('[name="role"]');
            const departmentSelect = form.querySelector('[name="department"]');
            const role = roleSelect.options[roleSelect.selectedIndex]?.text || roleSelect.value;
            const department = departmentSelect.options[departmentSelect.selectedIndex]?.text || departmentSelect.value || 'Not applicable';
            saveUserTitle.textContent = 'Create Staff Account';
            saveUserMessage.textContent = `Create the account for ${firstName} ${lastName} with username “${username}”, role “${role}”, and department “${department}”?`;
            confirmSaveUser.textContent = 'Create Account';
            saveUserModal.classList.add('open');
            saveUserModal.setAttribute('aria-hidden', 'false');
        });

        document.querySelectorAll('.delete-user-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingDeleteForm = form;
                deleteUserModal.classList.add('open');
                deleteUserModal.setAttribute('aria-hidden', 'false');
            });
        });

        function closeDeleteUserModal() {
            deleteUserModal.classList.remove('open');
            deleteUserModal.setAttribute('aria-hidden', 'true');
            pendingDeleteForm = null;
        }

        function closeSaveUserModal() {
            saveUserModal.classList.remove('open');
            saveUserModal.setAttribute('aria-hidden', 'true');
            pendingSaveForm = null;
        }

        cancelSaveUser.addEventListener('click', closeSaveUserModal);
        saveUserModal.addEventListener('click', (event) => {
            if (event.target === saveUserModal) {
                closeSaveUserModal();
            }
        });
        confirmSaveUser.addEventListener('click', () => {
            if (pendingSaveForm) {
                pendingSaveForm.submit();
            }
        });

        cancelDeleteUser.addEventListener('click', closeDeleteUserModal);
        deleteUserModal.addEventListener('click', (event) => {
            if (event.target === deleteUserModal) {
                closeDeleteUserModal();
            }
        });
        confirmDeleteUser.addEventListener('click', () => {
            if (pendingDeleteForm) {
                pendingDeleteForm.submit();
            }
        });
    </script>
    <script src="../public/js/sidebar.js"></script>
<?php if($cssrWorkspace): cssrUiEnd(); else: ?>
</body>
</html>
<?php endif; ?>
