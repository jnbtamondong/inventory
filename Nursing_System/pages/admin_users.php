<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('admin');
ensureUserRoleSchema($conn);
ensureDefaultAdminUser($conn);

$error = '';
$success = '';
$roleOptions = roleOptions();
$departmentOptions = departmentOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);

    if ($action === 'update_user') {
        $role = $_POST['role'] ?? '';
        $department = $_POST['department'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = $_POST['new_password'] ?? '';

        if ($userId <= 0) {
            $error = 'Invalid user selected.';
        } elseif (!isset($roleOptions[$role])) {
            $error = 'Please select a valid role.';
        } elseif ($department !== '' && $department !== null && !isset($departmentOptions[$department])) {
            $error = 'Please select a valid department.';
        } elseif ($userId === intval($_SESSION['user_id']) && ($role !== 'admin' || !$isActive)) {
            $error = 'You cannot remove admin access or deactivate your own account.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $department = $department === '' ? null : $department;
            $stmt = $conn->prepare("UPDATE users SET role = ?, department = ?, is_active = ? WHERE user_id = ?");
            $stmt->bind_param("ssii", $role, $department, $isActive, $userId);

            if ($stmt->execute()) {
                if ($newPassword !== '') {
                    $hashedPassword = hashPassword($newPassword);
                    $passwordStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $passwordStmt->bind_param("si", $hashedPassword, $userId);
                    if (!$passwordStmt->execute()) {
                        $error = 'User was updated, but password reset failed.';
                    }
                }

                if (!$error) {
                    $success = 'User updated successfully.';
                }
            } else {
                $error = 'Failed to update user.';
            }
        }
    }
}

$users = $conn->query("
    SELECT user_id, username, email, first_name, last_name, role, department, is_active, created_at, updated_at
    FROM users
    ORDER BY role = 'admin' DESC, last_name, first_name, username
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .admin-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .admin-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .admin-toolbar p {
            color: #667;
            margin-bottom: 0;
            max-width: 680px;
        }

        .user-form-grid {
            display: grid;
            grid-template-columns: minmax(180px, 1.2fr) minmax(150px, 1fr) minmax(150px, 1fr) minmax(170px, 1fr) 90px 110px;
            gap: 10px;
            align-items: end;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-meta {
            display: block;
            color: #667;
            font-size: 12px;
            margin-top: 3px;
        }

        .password-note {
            color: #667;
            font-size: 13px;
            margin-top: 8px;
        }

        @media (max-width: 1100px) {
            .user-form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .user-form-grid,
            .admin-toolbar {
                grid-template-columns: 1fr;
                display: grid;
            }

        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <h1>Nursing Service System</h1>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <section class="admin-panel">
                <div class="admin-toolbar">
                    <div>
                        <h2>User Management</h2>
                        <p>Admins can view usernames, change roles, activate or deactivate accounts, and set a new password. Existing passwords are encrypted, so the original plain text cannot be shown.</p>
                    </div>
                </div>
            </section>

            <section class="admin-panel">
                <h3>Users</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Account Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                        <span class="user-meta">Username: <?php echo htmlspecialchars($user['username']); ?></span>
                                        <span class="user-meta">Email: <?php echo htmlspecialchars($user['email']); ?></span>
                                        <span class="user-meta">Current role: <?php echo htmlspecialchars(roleLabel($user['role'])); ?></span>
                                        <span class="user-meta">Password: encrypted, reset only</span>
                                    </td>
                                    <td>
                                        <form method="POST" class="user-form-grid">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

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
                                                <label for="password-<?php echo htmlspecialchars($user['user_id']); ?>">New Password</label>
                                                <input type="password" id="password-<?php echo htmlspecialchars($user['user_id']); ?>" name="new_password" placeholder="Leave unchanged">
                                            </div>

                                            <div class="form-group">
                                                <label>Status</label>
                                                <label>
                                                    <input type="checkbox" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                                    Active
                                                </label>
                                            </div>

                                            <div class="form-group">
                                                <label>Updated</label>
                                                <span class="user-meta"><?php echo htmlspecialchars($user['updated_at'] ?? 'N/A'); ?></span>
                                            </div>

                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="password-note">For security, passwords are stored with one-way encryption. Admins can replace a user's password, but cannot recover the old one.</p>
            </section>

        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
