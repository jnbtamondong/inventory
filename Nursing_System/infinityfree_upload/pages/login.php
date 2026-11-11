<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

redirectIfLoggedIn();
ensureUserRoleSchema($conn);
ensureDefaultAdminUser($conn);

$error = '';
$success = '';
$activeTab = 'login';

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

function ensurePasswordResetSchema($conn) {
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

function currentBaseUrl() {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $path;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required';
        } else {
            $stmt = prepareQuery($conn, "SELECT user_id, username, password, role, department, first_name FROM users WHERE username = ? AND is_active = TRUE");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (validatePassword($password, $user['password'])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['department'] = $user['department'] ?? '';
                        $_SESSION['first_name'] = $user['first_name'];
                        
                        header('Location: ' . homePageForRole($user['role']));
                        exit();
                    } else {
                        $error = 'Invalid username or password';
                    }
                } else {
                    if (empty($error)) {
                        $error = 'Invalid username or password';
                    }
                }
            }
        }
    } elseif (isset($_POST['register'])) {
        $activeTab = 'register';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? '';
        $department = $_POST['department'] ?? '';
        $allowedRoles = registrationRoleOptions();
        $allowedDepartments = departmentOptions();
        
        if (empty($username) || empty($password) || empty($email) || empty($first_name) || empty($last_name) || empty($role) || empty($department)) {
            $error = 'All fields are required';
        } elseif (!isset($allowedRoles[$role])) {
            $error = 'Please select a valid role';
        } elseif (!isset($allowedDepartments[$department])) {
            $error = 'Please select a valid department';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $stmt = prepareQuery($conn, "SELECT user_id FROM users WHERE username = ? OR email = ?", $error);
            if ($stmt) {
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Username or email already exists';
                } else {
                    $hashedPassword = hashPassword($password);
                    $stmt = prepareQuery($conn, "INSERT INTO users (username, password, email, first_name, last_name, role, department) VALUES (?, ?, ?, ?, ?, ?, ?)", $error);
                    if ($stmt) {
                        $stmt->bind_param("sssssss", $username, $hashedPassword, $email, $first_name, $last_name, $role, $department);
                        
                        if ($stmt->execute()) {
                            $success = 'Registration successful! Please login.';
                            $activeTab = 'login';
                        } else {
                            $error = 'Registration failed. Please try again.';
                        }
                    }
                }
            }
        }
    } elseif (isset($_POST['forgot_password'])) {
        $email = trim($_POST['email'] ?? '');
        $activeTab = 'forgot';

        if (empty($email)) {
            $error = 'Email is required';
        } else {
            ensurePasswordResetSchema($conn);
            $stmt = prepareQuery($conn, "SELECT user_id, first_name, email FROM users WHERE email = ? AND is_active = TRUE", $error);
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();

                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $cleanup = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                    $cleanup->bind_param("i", $user['user_id']);
                    $cleanup->execute();

                    $resetStmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                    $resetStmt->bind_param("iss", $user['user_id'], $tokenHash, $expiresAt);
                    $resetStmt->execute();

                    $resetLink = currentBaseUrl() . '/reset_password.php?token=' . urlencode($token);
                    $subject = 'Nursing Service System Password Reset';
                    $message = "Hello " . $user['first_name'] . ",\n\nUse this link to reset your password. The link expires in 1 hour:\n\n" . $resetLink . "\n\nIf you did not request this, you can ignore this email.";
                    $headers = "From: no-reply@nursing-system.local\r\n";
                    $mailSent = mail($user['email'], $subject, $message, $headers);

                    if ($mailSent) {
                        $success = 'A password reset link was sent to your email.';
                        $activeTab = 'login';
                    } else {
                        $success = 'Password reset link created, but email sending is not configured on this server. Open this reset link: ' . $resetLink;
                    }
                } else {
                    $success = 'If that email exists, a password reset link was sent.';
                    $activeTab = 'login';
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
    <title>Nursing Service System - Login</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>🏥 Nursing Service System</h1>
            </div>

            <!-- Tabs -->
            <div class="auth-tabs">
                <button class="tab-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" onclick="switchTab('login', event)">Login</button>
                <button class="tab-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" onclick="switchTab('register', event)">Register</button>
                <button class="tab-btn <?php echo $activeTab === 'forgot' ? 'active' : ''; ?>" onclick="switchTab('forgot', event)">Forgot Password</button>
            </div>

            <!-- Login Form -->
            <div id="login" class="tab-content <?php echo $activeTab === 'login' ? 'active' : ''; ?>">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <input type="text" id="login-username" name="username" required placeholder="Enter your username">
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" required placeholder="Enter your password">
                    </div>

                    <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
                </form>
            </div>

            <!-- Register Form -->
            <div id="register" class="tab-content <?php echo $activeTab === 'register' ? 'active' : ''; ?>">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first-name">First Name</label>
                            <input type="text" id="first-name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name">Last Name</label>
                            <input type="text" id="last-name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg-username">Username</label>
                        <input type="text" id="reg-username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-email">Email</label>
                        <input type="email" id="reg-email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <input type="password" id="reg-password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <?php foreach (registrationRoleOptions() as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach (departmentOptions() as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="register" class="btn btn-primary btn-block">Register</button>
                </form>
            </div>

            <!-- Forgot Password Form -->
            <div id="forgot" class="tab-content <?php echo $activeTab === 'forgot' ? 'active' : ''; ?>">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="forgot-email">Email</label>
                        <input type="email" id="forgot-email" name="email" required placeholder="Enter your account email">
                    </div>

                    <button type="submit" name="forgot_password" class="btn btn-primary btn-block">Send Reset Link</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../public/js/auth.js"></script>
</body>
</html>

