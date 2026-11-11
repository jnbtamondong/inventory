<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

redirectIfLoggedIn();

$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('PENDING','RESOLVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    INDEX idx_reset_request_status(status),
    INDEX idx_reset_request_user(user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

$error = '';
$success = '';
consumeFlashMessages($error, $success);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = sanitizeText($_POST['identity'] ?? '', 100);
    if ($identity === '') {
        setFlashMessage('error', 'Enter your username or employee ID.');
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR employee_id = ?) AND is_active = TRUE AND COALESCE(status,'ACTIVE')='ACTIVE' LIMIT 1");
        $stmt->bind_param('ss', $identity, $identity);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $userId = (int) $user['user_id'];
            $pending = $conn->prepare("SELECT request_id FROM password_reset_requests WHERE user_id=? AND status='PENDING' LIMIT 1");
            $pending->bind_param('i', $userId);
            $pending->execute();
            if (!$pending->get_result()->fetch_assoc()) {
                $create = $conn->prepare("INSERT INTO password_reset_requests(user_id) VALUES(?)");
                $create->bind_param('i', $userId);
                $create->execute();
            }
        }
        setFlashMessage('success', 'Your password reset request was submitted. Contact the System Administrator for your new password.');
    }
    redirectAfterPost('forgot_password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | NURSYNC</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/auth.css?v=20260917-02">
    <link rel="stylesheet" href="../public/css/login.css?v=4">
    <style>
        .forgot-card{max-width:470px;margin:auto}.forgot-copy{color:#60788a;line-height:1.65;margin:0 0 20px}.forgot-card .auth-header{margin-bottom:18px}.forgot-actions{display:grid;gap:10px;margin-top:18px}
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="hospital-auth-page nursync-login">
    <div class="hospital-motion-bg" aria-hidden="true"><canvas id="hospitalMotionCanvas"></canvas><span class="hospital-grid-overlay"></span><span class="hospital-vignette-overlay"></span></div>
    <div class="auth-container">
        <main class="login-layout">
            <section class="login-welcome" aria-labelledby="forgot-title">
                <a class="login-brand" href="../index.php"><img src="../public/images/branding/jvgh_seal.png" alt="JVGH seal"><span><strong>NURSYNC</strong><small>Joni Villanueva General Hospital</small></span></a>
                <div class="login-welcome-copy"><span class="login-eyebrow">ACCOUNT RECOVERY</span><h2 id="forgot-title">Recover access.<br><span>Stay connected.</span></h2><p>Submit a secure request for the System Administrator to reset your password.</p></div>
            </section>
            <div class="auth-card forgot-card">
                <div class="auth-header"><span class="login-eyebrow">PASSWORD ASSISTANCE</span><h1 class="login-hospital-title">Forgot password?</h1></div>
                <p class="forgot-copy">Enter your username or employee ID. For security, the system gives the same response whether an account is found or not.</p>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST" class="auth-form">
                    <div class="form-group"><label for="identity">Username or Employee ID</label><input type="text" id="identity" name="identity" required autocomplete="username" placeholder="Enter username or employee ID"></div>
                    <div class="forgot-actions"><button class="btn btn-primary btn-block" type="submit">Submit reset request</button><a class="btn btn-home btn-block" href="login.php">Back to login</a></div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
