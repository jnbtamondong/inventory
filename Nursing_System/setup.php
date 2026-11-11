<?php
// Destructive installation is disabled unless an operator explicitly enables it.
// Check this before opening a database connection or loading any schema helpers.
if (getenv('NURSING_ENABLE_SETUP') !== '1') {
    http_response_code(403);
    exit('Database setup is disabled. Contact the system administrator.');
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (!isSuperAdmin()) {
    http_response_code(403);
    exit('Administrator access is required.');
}
requireCsrfToken();
// Database Setup Script
// This file initializes the database schema and required reference data.

// Create database connection first (without selecting database)
$db_host = DB_HOST;
$db_user = DB_USER;
$db_pass = DB_PASS;
$db_name = DB_NAME;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, '', DB_PORT);
} catch (mysqli_sql_exception $error) {
    error_log('[SETUP CONNECT] ' . $error->getMessage());
    http_response_code(500);
    exit('Setup connection failed. Contact the system administrator.');
}

require_once 'includes/auth.php';
require_once 'includes/locations.php';
require_once 'includes/patient_helpers.php';

$setup_complete = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup'])) {
    try {
        // Drop existing database if it exists
        $drop_db = "DROP DATABASE IF EXISTS `" . $db_name . "`";
        $conn->query($drop_db);
        
        // Create database
        $create_db = "CREATE DATABASE IF NOT EXISTS `" . $db_name . "`";
        if (!$conn->query($create_db)) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select database
        if (!$conn->select_db($db_name)) {
            throw new Exception("Error selecting database: " . $conn->error);
        }
        
        // Read and execute schema
        $schema_file = 'database/schema.sql';
        if (file_exists($schema_file)) {
            $schema = file_get_contents($schema_file);
            // Remove comments and split by semicolon
            $lines = explode("\n", $schema);
            $statement = "";
            
            foreach ($lines as $line) {
                // Skip empty lines and comments
                $line = trim($line);
                if (empty($line) || strpos($line, '--') === 0) {
                    continue;
                }
                
                $statement .= " " . $line;
                
                // If line ends with semicolon, execute the statement
                if (substr($line, -1) === ';') {
                    $statement = trim(substr($statement, 0, -1)); // Remove trailing semicolon
                    if (!empty($statement)) {
                        if (!$conn->query($statement)) {
                            throw new Exception("SQL Error: " . $conn->error . " | Statement: " . substr($statement, 0, 100));
                        }
                    }
                    $statement = "";
                }
            }
        }

        $locationResult = insertLocationData($conn);
        if ($locationResult !== true) {
            throw new Exception($locationResult);
        }

        $bedResult = ensureEmergencyRoomBeds($conn);
        if ($bedResult !== true) {
            throw new Exception($bedResult);
        }

        $adminResult = ensureDefaultAdminUser($conn);
        if ($adminResult !== true) {
            throw new Exception('Failed to create default admin user.');
        }

        $setup_complete = true;
    } catch (Exception $e) {
        $error = 'Setup Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Nursing Service System</title>
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        .setup-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .setup-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🏥 Nursing Service System Setup</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <h3>❌ Setup Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <p style="margin-top: 10px;"><a href="setup.php" class="btn btn-secondary">Try Again</a></p>
            </div>
        <?php elseif ($setup_complete): ?>
            <div class="setup-success">
                <h3>✅ Setup Completed Successfully!</h3>
                <p>The database has been initialized with an empty data set.</p>
                <p style="margin-top: 20px;"><a href="pages/login.php" class="btn btn-primary">Go to Login →</a></p>
            </div>
        <?php else: ?>
            <div class="setup-info">
                <h3>Database Setup</h3>
                <p>This will initialize the database with:</p>
                <ul>
                    <li>Create nursing_system database</li>
                    <li>Create all required tables</li>
                    <li>Required location reference data</li>
                </ul>
            </div>

            <form method="POST">
                <button type="submit" name="setup" class="btn btn-primary btn-block" style="padding: 15px; font-size: 16px;">
                    🔧 Initialize Database
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
