<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (request_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,status ENUM('PENDING','RESOLVED','CANCELLED') NOT NULL DEFAULT 'PENDING',requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,resolved_at DATETIME NULL,resolved_by INT NULL,INDEX idx_reset_request_status(status),INDEX idx_reset_request_user(user_id),FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_password_reset') {
    $pendingStmt = $conn->prepare("SELECT request_id FROM password_reset_requests WHERE user_id=? AND status='PENDING' LIMIT 1");
    $pendingStmt->bind_param('i', $userId);
    $pendingStmt->execute();
    if (!$pendingStmt->get_result()->fetch_assoc()) {
        $requestStmt = $conn->prepare("INSERT INTO password_reset_requests(user_id) VALUES(?)");
        $requestStmt->bind_param('i', $userId);
        $requestStmt->execute();
    }
    setFlashMessage('success', 'Password reset request submitted to the System Administrator.');
    redirectAfterPost('account_settings.php'.(($_GET['workspace']??'')==='cssr'?'?workspace=cssr':''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '' || $lastName === '') {
        $error = 'First name and last name are required.';
    } elseif ($email !== '') {
        $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
        $emailCheck->bind_param("si", $email, $userId);
        $emailCheck->execute();
        $existingEmail = $emailCheck->get_result()->fetch_assoc();

        if ($existingEmail) {
            $error = 'Email is already used by another account.';
        }
    }

    if ($error === '' && ($currentPassword === '' || $newPassword === '' || $confirmPassword === '')) {
        $error = 'Current password, new password, and confirm password are required.';
    }

    if ($error === '') {
        $passwordStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $passwordStmt->bind_param("i", $userId);
        $passwordStmt->execute();
        $passwordRow = $passwordStmt->get_result()->fetch_assoc();

        if (!$passwordRow || !validatePassword($currentPassword, $passwordRow['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif ($policyError = passwordPolicyError($newPassword, $_SESSION['username'] ?? '', $email)) {
            $error = $policyError;
        } else {
            $passwordHash = hashPassword($newPassword);
            $emailForSave = $email === '' ? null : $email;
            $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password = ? WHERE user_id = ?");
            $updateStmt->bind_param("sssssi", $firstName, $lastName, $emailForSave, $phone, $passwordHash, $userId);
            $success = $updateStmt->execute() ? 'Account settings updated.' : 'Failed to update account settings.';
        }
    }

    if ($success !== '') {
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('account_settings.php'.(($_GET['workspace']??'')==='cssr'?'?workspace=cssr':''));
}

$stmt = $conn->prepare("SELECT username, email, first_name, last_name, role, department, phone FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: ../api/logout.php');
    exit();
}
$pendingResetStmt = $conn->prepare("SELECT requested_at FROM password_reset_requests WHERE user_id=? AND status='PENDING' ORDER BY requested_at DESC LIMIT 1");
$pendingResetStmt->bind_param('i', $userId);
$pendingResetStmt->execute();
$pendingResetRequest = $pendingResetStmt->get_result()->fetch_assoc();
$cssrWorkspace=($_GET['workspace']??'')==='cssr';
?>
<?php if($cssrWorkspace): require_once __DIR__.'/../CSSR_INVENTORY/includes/external-workspace.php'; cssrExternalStart('Account Settings','settings','account-settings-page',['/Nursing_System/public/css/style.css','/Nursing_System/public/css/dashboard.css']); else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui account-settings-page">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container account-settings-shell">
        <main class="main-content standalone-main">
<?php endif; ?>
            <section class="form-section">
                <h2>Account Settings</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <form method="POST" id="account-settings-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars(roleLabel($user['role'])); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first-name">First Name *</label>
                            <input type="text" id="first-name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name">Last Name *</label>
                            <input type="text" id="last-name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email <small>(Optional)</small></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <h3 style="margin-top: 18px;">Change Password</h3>
                    <p class="compact-meta">Enter your current password and matching new password to save account settings.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <div class="password-field"><input type="password" id="current-password" name="current_password" autocomplete="current-password" required><button type="button" class="password-view" data-password-view="current-password" aria-label="Show password">&#128065;</button></div>
                        </div>
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <div class="password-field"><input type="password" id="new-password" name="new_password" autocomplete="new-password" minlength="10" required><button type="button" class="password-view" data-password-view="new-password" aria-label="Show password">&#128065;</button></div>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <div class="password-field"><input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" minlength="10" required><button type="button" class="password-view" data-password-view="confirm-password" aria-label="Show password">&#128065;</button></div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary" id="cancel-account-settings">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>

                <div class="forgot-password-settings">
                    <div><h3>Forgot Password</h3><p class="compact-meta">Submit a request if you cannot provide your current password. The System Administrator will assign a new temporary password.</p></div>
                    <?php if ($pendingResetRequest): ?>
                        <span class="reset-request-pending">Request pending</span>
                    <?php else: ?>
                        <form method="POST"><input type="hidden" name="action" value="request_password_reset"><button type="submit" class="btn btn-secondary">Request Password Reset</button></form>
                    <?php endif; ?>
                </div>
            </section>
<?php if(!$cssrWorkspace): ?>        </main>
    </div><?php endif; ?>
    <script src="../public/js/sidebar.js"></script>
    <div id="account-confirm-modal" class="account-confirm-modal" aria-hidden="true">
        <div class="account-confirm-card" role="dialog" aria-modal="true">
            <h3>Confirm Account Changes</h3>
            <p class="compact-meta">Are you sure you want to save these account settings?</p>
            <div class="account-confirm-actions">
                <button type="button" class="btn btn-secondary" id="cancel-account-save">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-account-save">Confirm</button>
            </div>
        </div>
    </div>
    <div id="account-cancel-modal" class="account-confirm-modal" aria-hidden="true">
        <div class="account-confirm-card" role="dialog" aria-modal="true">
            <h3>Cancel Changes</h3>
            <p class="compact-meta">Are you sure you want to cancel? Unsaved changes will be lost.</p>
            <div class="account-confirm-actions">
                <button type="button" class="btn btn-secondary" id="keep-account-editing">Keep Editing</button>
                <button type="button" class="btn btn-danger" id="confirm-account-cancel">Cancel Changes</button>
            </div>
        </div>
    </div>
    <style>
        .account-confirm-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(31, 41, 55, 0.55);
            padding: 20px;
            z-index: 1000;
        }

        .account-confirm-modal.open {
            display: flex;
        }

        .account-confirm-card {
            width: min(460px, 100%);
            background: #ffffff;
            border: 1px solid #d7e8e3;
            border-radius: 8px;
            box-shadow: 0 18px 46px rgba(15, 55, 88, 0.24);
            padding: 22px;
        }

        .account-confirm-card h3 {
            margin: 0 0 8px;
            color: #0B3C5D;
        }

        .account-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .standalone-main {
            width: min(980px, 100%);
            margin: 0 auto;
        }

        .account-settings-shell {
            display: block;
            min-height: calc(100vh - 70px);
            padding: 24px;
        }

        .account-settings-shell .form-section {
            width: 100%;
        }

        .forgot-password-settings { display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:22px;padding:18px;border:1px solid #328cc133;border-left:4px solid #f4b860;border-radius:12px;background:#fffaf0; }
        .forgot-password-settings h3 { margin:0 0 5px;color:#0b3c5d; }
        .forgot-password-settings p { margin:0; }
        .reset-request-pending { display:inline-flex;padding:8px 12px;border-radius:999px;background:#fff0cf;color:#805500;font-size:12px;font-weight:800;white-space:nowrap; }
        .password-field { position:relative;display:flex;align-items:center; }
        .password-field input { width:100%;padding-right:58px!important; }
        .password-view { position:absolute;right:7px;display:grid;place-items:center;width:34px;height:34px;border:0;background:transparent;color:#2378aa;font-size:18px;cursor:pointer; }

        @media (max-width: 768px) {
            .account-settings-shell {
                padding: 14px;
            }
            .forgot-password-settings { align-items:flex-start;flex-direction:column; }
        }
    </style>
    <script>
        const accountSettingsForm = document.getElementById('account-settings-form');
        const accountConfirmModal = document.getElementById('account-confirm-modal');
        const cancelAccountSave = document.getElementById('cancel-account-save');
        const confirmAccountSave = document.getElementById('confirm-account-save');
        const cancelAccountSettings = document.getElementById('cancel-account-settings');
        const accountCancelModal = document.getElementById('account-cancel-modal');
        const keepAccountEditing = document.getElementById('keep-account-editing');
        const confirmAccountCancel = document.getElementById('confirm-account-cancel');
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const initialAccountFormState = new FormData(accountSettingsForm);
        let accountSaveConfirmed = false;

        document.querySelectorAll('[data-password-view]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordView);
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.innerHTML = showing ? '&#128065;' : '&#128065;&#xFE0E;';
            });
        });

        function accountFormChanged() {
            const currentState = new FormData(accountSettingsForm);
            for (const [key, value] of currentState.entries()) {
                if ((initialAccountFormState.get(key) || '') !== value) {
                    return true;
                }
            }
            return false;
        }

        function validateMatchingPasswords() {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('New password and confirmation do not match.');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }

        newPasswordInput.addEventListener('input', validateMatchingPasswords);
        confirmPasswordInput.addEventListener('input', validateMatchingPasswords);

        accountSettingsForm.addEventListener('submit', (event) => {
            if (accountSaveConfirmed) {
                return;
            }

            validateMatchingPasswords();
            if (!accountSettingsForm.checkValidity()) {
                return;
            }

            event.preventDefault();
            accountConfirmModal.classList.add('open');
            accountConfirmModal.setAttribute('aria-hidden', 'false');
        });

        function closeAccountConfirmModal() {
            accountConfirmModal.classList.remove('open');
            accountConfirmModal.setAttribute('aria-hidden', 'true');
        }

        function closeAccountCancelModal() {
            accountCancelModal.classList.remove('open');
            accountCancelModal.setAttribute('aria-hidden', 'true');
        }

        cancelAccountSave.addEventListener('click', closeAccountConfirmModal);
        accountConfirmModal.addEventListener('click', (event) => {
            if (event.target === accountConfirmModal) {
                closeAccountConfirmModal();
            }
        });

        confirmAccountSave.addEventListener('click', () => {
            accountSaveConfirmed = true;
            closeAccountConfirmModal();
            accountSettingsForm.submit();
        });

        cancelAccountSettings.addEventListener('click', () => {
            if (accountFormChanged()) {
                accountCancelModal.classList.add('open');
                accountCancelModal.setAttribute('aria-hidden', 'false');
                return;
            }

            window.location.href = <?=json_encode($cssrWorkspace?'/Nursing_System/CSSR_INVENTORY/pages/dashboard.php':'system_menu.php')?>;
        });

        keepAccountEditing.addEventListener('click', closeAccountCancelModal);
        accountCancelModal.addEventListener('click', (event) => {
            if (event.target === accountCancelModal) {
                closeAccountCancelModal();
            }
        });
        confirmAccountCancel.addEventListener('click', () => {
            window.location.href = <?=json_encode($cssrWorkspace?'/Nursing_System/CSSR_INVENTORY/pages/dashboard.php':'system_menu.php')?>;
        });
    </script>
<?php if($cssrWorkspace): cssrUiEnd(); else: ?>
</body>
</html>
<?php endif; ?>
