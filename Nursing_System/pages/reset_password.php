<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = null;

function ensurePasswordResetSchemaForReset($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS password_resets (
            reset_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_password_reset_token (token_hash),
            INDEX idx_password_reset_user (user_id)
        )
    ");
}

ensurePasswordResetSchemaForReset($conn);

if (empty($token)) {
    $error = 'Invalid password reset link.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT pr.reset_id, pr.user_id, u.username, u.email
        FROM password_resets pr
        INNER JOIN users u ON pr.user_id = u.user_id
        WHERE pr.token_hash = ?
            AND pr.used_at IS NULL
            AND pr.expires_at > NOW()
            AND u.is_active = TRUE
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();

    if (!$reset) {
        $error = 'This password reset link is invalid or expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password']) && $reset) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $error = 'New password and confirmation are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif ($policyError = passwordPolicyError($password, $reset['username'] ?? '', $reset['email'] ?? '')) {
        $error = $policyError;
    } else {
        $hashedPassword = hashPassword($password);
        $updateUser = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateUser->bind_param("si", $hashedPassword, $reset['user_id']);
        $updateUser->execute();

        $markUsed = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?");
        $markUsed->bind_param("i", $reset['reset_id']);
        $markUsed->execute();

        setFlashMessage('success', 'Password reset successful. You can now login.');
        redirectAfterPost('login.php');
    }

    if ($error) {
        setFlashMessage('error', $error);
        redirectAfterPost('reset_password.php?token=' . urlencode($token));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/auth.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui ns-auth-reset">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Nursing Information System</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <p><a href="login.php" class="btn btn-primary btn-block">Go to Login</a></p>
            <?php endif; ?>

            <?php if ($reset): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" minlength="10" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm New Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" minlength="10" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary btn-block">Reset Password</button>
                </form>
            <?php elseif (!$success): ?>
                <p><a href="login.php" class="btn btn-primary btn-block">Back to Login</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
