<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

redirectIfLoggedIn();
ensureUserRoleSchema($conn);
ensureDefaultAdminUser($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);
if (isset($_GET['timeout'])) {
    $error = 'Your session expired due to inactivity. Please log in again.';
}

function prepareQuery($conn, $query, &$error = null) {
    try {
        return $conn->prepare($query);
    } catch (mysqli_sql_exception $e) {
        if ($error !== null) {
            $error = 'Database setup is required. Please visit ../setup.php to initialize the database.';
        }
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $username = sanitizeText($_POST['username'] ?? '', 100);
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            setFlashMessage('error', 'Username and password are required');
            redirectAfterPost('login.php');
        } elseif (isLoginLocked($conn, $username)) {
            logSecurityEvent($conn, 'Login Locked', 'Too many failed attempts for username: ' . $username);
            setFlashMessage('error', 'Too many failed login attempts. Please wait 15 minutes and try again.');
            redirectAfterPost('login.php');
        } else {
            $stmt = prepareQuery($conn, "SELECT user_id, username, password, role, department, first_name FROM users WHERE username = ? AND is_active = TRUE AND COALESCE(status, 'ACTIVE') = 'ACTIVE'");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (validatePassword($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = canonicalRole($user['role']);
                        $_SESSION['department'] = canonicalDepartment($user['department'] ?? '');
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                            $newHash = hashPassword($password);
                            $rehashStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                            $rehashStmt->bind_param("si", $newHash, $user['user_id']);
                            $rehashStmt->execute();
                        }

                        recordLoginAttempt($conn, $username, true);
                        logSecurityEvent($conn, 'Login', 'Successful login', (int) $user['user_id']);
                        
                        header('Location: system_menu.php');
                        exit();
                    } else {
                        recordLoginAttempt($conn, $username, false);
                        logSecurityEvent($conn, 'Failed Login', 'Invalid password for username: ' . $username);
                        setFlashMessage('error', 'Invalid username or password');
                        redirectAfterPost('login.php');
                    }
                } else {
                    if (empty($error)) {
                        recordLoginAttempt($conn, $username, false);
                        logSecurityEvent($conn, 'Failed Login', 'Unknown or inactive username: ' . $username);
                        setFlashMessage('error', 'Invalid username or password');
                        redirectAfterPost('login.php');
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NURSYNC</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/auth.css?v=20260917-02">
    <link rel="stylesheet" href="../public/css/login.css?v=8">
    <link rel="stylesheet" href="../public/css/module-theme.css?v=18">
    <script defer src="../public/js/nursync-ui.js?v=4"></script>
</head>
<body class="hospital-auth-page nursync-login">
    <div class="hospital-motion-bg" aria-hidden="true">
        <canvas id="hospitalMotionCanvas"></canvas>
        <img src="../public/images/branding/jvgh_seal.png" alt="">
        <span class="hospital-grid-overlay"></span>
        <span class="hospital-vignette-overlay"></span>
        <div class="login-background-art">
            <span class="login-orbit login-orbit-one"></span>
            <span class="login-orbit login-orbit-two"></span>
            <span class="login-medical-mark mark-one">+</span>
            <span class="login-medical-mark mark-two">+</span>
            <span class="login-medical-mark mark-three">+</span>
            <span class="login-medical-mark mark-four">+</span>
            <span class="login-medical-mark mark-five">+</span>
            <span class="login-medical-mark mark-six">+</span>
            <svg class="login-health-icon login-heart" viewBox="0 0 100 100"><path d="M50 82 18 52C-4 27 28 5 50 29 72 5 104 27 82 52Z"/><path class="health-detail" d="M18 49H35L43 35L53 65L62 46H83"/></svg>
            <svg class="login-health-icon login-shield" viewBox="0 0 100 100"><path d="M50 8 83 21V48C83 69 66 84 50 93 34 84 17 69 17 48V21Z"/><path class="health-detail" d="M50 33V66M34 49H66"/></svg>
            <svg class="login-pulse-line pulse-top" viewBox="0 0 1400 200" preserveAspectRatio="none"><path d="M0 100H180L215 65L245 130L280 25L320 175L350 90L385 100H900L940 70L975 125L1010 40L1050 160L1085 100H1400"/></svg>
            <svg class="login-pulse-line" viewBox="0 0 1400 200" preserveAspectRatio="none"><path d="M0 110H220L250 85L280 135L320 30L360 175L395 90L425 110H950L990 85L1020 130L1055 45L1090 160L1125 110H1400"/></svg>
        </div>
    </div>
    <div class="auth-container">
        <main class="login-layout">
        <section class="login-welcome" aria-labelledby="login-welcome-title">
            <a class="login-brand" href="../index.php"><img src="../public/images/branding/jvgh_seal.png" alt="JVGH seal"><span><strong>NURSYNC</strong><small>Joni Villanueva General Hospital</small></span></a>
            <div class="login-welcome-copy"><span class="login-eyebrow">JVGH NURSING MANAGEMENT SYSTEM</span><h2 id="login-welcome-title">Connecting Nurses.<br><span>Coordinating Care.</span></h2><p>Your nursing operations, resources, and staff records in one connected workspace.</p><div class="login-workspaces"><span><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i> Nursing operations</span><span><i class="bi bi-box-seam" aria-hidden="true"></i> CSSR inventory</span><span><i class="bi bi-file-earmark-person" aria-hidden="true"></i> Staff documents</span></div></div>
            <p class="login-welcome-footer"><span aria-hidden="true"></span>One nursing system. Everything connected.</p>
        </section>
        <div class="auth-card">
            <div class="auth-header">
                <span class="login-eyebrow">WELCOME TO NURSYNC</span>
                <h1 class="login-hospital-title">Welcome back.</h1>
                <p class="login-tagline">Sign in to continue to your workspace.</p>
            </div>

            <!-- Login Form -->
            <div id="login" class="tab-content active">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-person login-input-icon" aria-hidden="true"></i>
                            <input type="text" id="login-username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="Enter your username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="login-input-wrap login-password-wrap">
                            <i class="bi bi-key login-input-icon" aria-hidden="true"></i>
                            <input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="Enter your password">
                            <button class="login-password-toggle" type="button" aria-label="Show password" aria-controls="login-password" aria-pressed="false" title="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary btn-block"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign in</button>
                    <a href="../index.php" class="btn btn-home btn-block"><i class="bi bi-house" aria-hidden="true"></i> Back to homepage</a>
                </form>
                <p class="login-assistance">Need help accessing your account?<br>Contact your system administrator.</p>
            </div>
        </div>
        </main>
    </div>

    <script src="../public/js/auth.js"></script>
    <script src="../public/js/login-presentation.js?v=1"></script>
</body>
</html>
