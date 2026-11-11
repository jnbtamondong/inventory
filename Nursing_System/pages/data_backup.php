<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if (!hasRole('SUPER_ADMIN')) {
    logSecurityEvent($conn, 'Restricted Access Attempt', 'Data Backup module blocked');
    http_response_code(403);
    exit('Access denied for your role.');
}

$backupDirectory = realpath(__DIR__ . '/../database') . DIRECTORY_SEPARATOR . 'manual_backups';
$settingsFile = realpath(__DIR__ . '/../database') . DIRECTORY_SEPARATOR . 'backup_settings.json';
$taskName = 'JVGH Nursing System Daily Backup';
$scheduleDefaults = ['enabled' => false, 'time' => '02:00', 'retention_days' => 30];
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true)) {
    throw new RuntimeException('Unable to create the manual backup folder.');
}

require_once __DIR__.'/../includes/database-backup.php';
function createDatabaseDump(string $database, string $target): void
{
    nursingCreateDatabaseBackup(nursingBackupConfig($database), $target, nursingLegacyBackupExclusions($database));
}
function runTaskCommand(array $command): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__ . '/..');
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to update the Windows backup schedule.');
    }
    fclose($pipes[0]);
    $output = trim(stream_get_contents($pipes[1]));
    $error = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), trim($output . ($error !== '' ? "\n" . $error : ''))];
}

$schedule = $scheduleDefaults;
if (is_file($settingsFile)) {
    $savedSchedule = json_decode((string) file_get_contents($settingsFile), true);
    if (is_array($savedSchedule)) {
        $schedule = array_merge($scheduleDefaults, array_intersect_key($savedSchedule, $scheduleDefaults));
    }
}

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$backupStatus = [];
$statusPath = __DIR__.'/../database/backup_status.json';
if (is_file($statusPath)) {
    $decoded = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($statusPath)), true);
    if (is_array($decoded)) $backupStatus = $decoded;
}
$secondaryCopy = $savedSchedule['secondary_copy'] ?? [];
$latestRun = 'Not run yet';
if (!empty($backupStatus['finished_at'])) {
    try { $latestRun = (new DateTimeImmutable($backupStatus['finished_at']))->setTimezone(new DateTimeZone('Asia/Singapore'))->format('M d, Y h:i A').' (UTC+08:00)'; } catch (Throwable $ignored) {}
}

if (isset($_GET['download'])) {
    $name = basename((string) $_GET['download']);
    if (!preg_match('/^(nursing_system|cssr_inventory)_manual_\d{8}_\d{6}\.sql$/', $name)) {
        http_response_code(400);
        exit('Invalid backup file.');
    }
    $base = realpath($backupDirectory);
    $path = realpath($backupDirectory . DIRECTORY_SEPARATOR . $name);
    if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        http_response_code(404);
        exit('Backup file not found.');
    }
    logSecurityEvent($conn, 'Manual Backup Downloaded', $name);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = (string) ($_POST['action'] ?? 'manual_backup');
    $password = (string) ($_POST['admin_password'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare('SELECT password FROM users WHERE user_id=? AND role="SUPER_ADMIN" AND status="ACTIVE"');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $passwordHash = $stmt->get_result()->fetch_row()[0] ?? '';
    if ($password === '' || !validatePassword($password, $passwordHash)) {
        logSecurityEvent($conn, 'Backup Action Denied', 'Invalid administrator password');
        setFlashMessage('error', 'The administrator password is incorrect.');
        redirectAfterPost('data_backup.php');
    }
    if ($action === 'save_schedule') {
        $enabled = isset($_POST['automatic_enabled']);
        $time = trim((string) ($_POST['backup_time'] ?? ''));
        $retentionDays = filter_var($_POST['retention_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) || $retentionDays === false) {
            setFlashMessage('error', 'Enter a valid backup time and a retention period from 1 to 365 days.');
            redirectAfterPost('data_backup.php');
        }
        try {
            $scriptPath = realpath(__DIR__ . '/../tools/configure-backup-schedule.ps1');
            if (!$scriptPath) throw new RuntimeException('The backup scheduler was not found.');
            $command = ['C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $scriptPath, '-At', $time, '-RetentionDays', (string) $retentionDays];
            if (!$enabled) $command[] = '-Disable';
            [$exitCode, $taskOutput] = runTaskCommand($command);
            if ($exitCode !== 0) {
                error_log('Backup scheduler failed: '.$taskOutput);
                throw new RuntimeException('Windows could not update the backup schedule. Ask the server administrator to check Task Scheduler permissions.');
            }
            // The scheduler preserves the secondary destination and service identity.
            $schedule = array_merge($schedule, ['enabled' => $enabled, 'time' => $time, 'retention_days' => (int) $retentionDays]);
            logSecurityEvent($conn, 'Automatic Backup Settings Updated', ($enabled ? 'Enabled' : 'Disabled') . ' at ' . $time . ', retention ' . $retentionDays . ' days');
            setFlashMessage('success', $enabled ? 'Automatic backup is enabled and scheduled daily at ' . date('g:i A', strtotime($time)) . '.' : 'Automatic backup is disabled. Manual backups remain available.');
        } catch (Throwable $exception) {
            setFlashMessage('error', $exception->getMessage());
        }
    } elseif ($action === 'manual_backup') {
        $timestamp = date('Ymd_His');
        $created = [];
        try {
            foreach (['nursing_system', 'cssr_inventory'] as $database) {
                $path = $backupDirectory . DIRECTORY_SEPARATOR . $database . '_manual_' . $timestamp . '.sql';
                createDatabaseDump($database, $path);
                $created[] = $path;
            }
            logSecurityEvent($conn, 'Manual Backup Created', 'Created both Nursing and CSSR backups');
            setFlashMessage('success', 'Database backups created. Keep both files together. All active application tables are included; excluded legacy backup tables are recorded in the manifest. Uploaded files require a separate backup.');
        } catch (Throwable $exception) {
            foreach ($created as $path) {
                @unlink($path);
            }
            setFlashMessage('error', $exception->getMessage());
        }
    } else {
        setFlashMessage('error', 'Invalid backup action.');
    }
    redirectAfterPost('data_backup.php');
}

$backupSets = [];
foreach (glob($backupDirectory . DIRECTORY_SEPARATOR . '*_manual_*.sql') ?: [] as $file) {
    if (preg_match('/^(nursing_system|cssr_inventory)_manual_(\d{8}_\d{6})\.sql$/', basename($file), $matches)) {
        $backupSets[$matches[2]][$matches[1]] = $file;
    }
}
krsort($backupSets);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Data Backup - JVGH Nursing</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css?v=20260916-1">
    <style>
        body.backup-page{min-height:100vh;overflow-y:auto!important;background:#f4f7fa;animation:none!important;transform:none!important}
        .backup-shell{width:min(1320px,calc(100% - 32px));margin:0 auto;padding:24px 0 56px}
        .backup-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px;padding:18px 20px;background:#fff;border:1px solid #d9e4f2;border-radius:8px;box-shadow:0 14px 34px rgba(11,60,93,.10)}
        .backup-brand{display:flex;align-items:center;gap:12px}.backup-brand img{width:52px;height:52px;object-fit:contain;border-radius:50%;background:#fff}
        .backup-brand span{display:block;color:#328CC1;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.backup-brand strong{display:block;color:#0B3C5D;font-size:20px;line-height:1.15}
        .backup-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
        .backup-panel{background:#fff;padding:20px;border-radius:8px;box-shadow:var(--shadow);margin-bottom:24px}
        .backup-toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.backup-toolbar p,.backup-note{color:#667;margin-bottom:0;max-width:720px}
        .backup-form{display:grid;grid-template-columns:minmax(260px,420px) auto;gap:12px;align-items:end;margin-top:18px}.backup-form .btn{height:42px}
        .schedule-grid{display:grid;grid-template-columns:minmax(210px,1fr) minmax(150px,.55fr) minmax(170px,.65fr);gap:14px;margin-top:18px;align-items:end}.schedule-actions{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin-top:14px}.schedule-actions .form-group{min-width:260px;margin:0}.schedule-toggle{display:flex;align-items:center;gap:10px;min-height:42px;padding:10px 12px;border:1px solid #c9d8e8;border-radius:6px;background:#f8fbfd}.schedule-toggle input{width:18px;height:18px}.schedule-state{display:inline-block;padding:6px 11px;border-radius:999px;font-size:12px;font-weight:900}.schedule-state.on{background:#e6f6ee;color:#13734b}.schedule-state.off{background:#eef2f6;color:#526271}
        .backup-page .table-responsive{overflow-x:auto;padding-bottom:10px}.backup-page .table td{vertical-align:middle}
        .backup-status{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800}.backup-status.complete{background:#e6f6ee;color:#13734b}.backup-status.incomplete{background:#fff0df;color:#9a5a00}
        .backup-empty{text-align:center!important;padding:30px!important;color:#667}
        @media(max-width:760px){.backup-header{display:block}.backup-actions{justify-content:flex-start;margin-top:14px}.backup-form,.schedule-grid{grid-template-columns:1fr}.backup-form .btn,.schedule-actions .btn{width:100%}.schedule-actions .form-group{min-width:100%;width:100%}}
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui backup-page">
<main class="backup-shell">
    <header class="backup-header">
        <div class="backup-brand"><img src="../public/images/branding/jvgh_seal.png" alt="Joni Villanueva General Hospital logo"><div><span>Joni Villanueva General Hospital</span><strong>System Data Backup</strong></div></div>
        <div class="backup-actions"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?> (<?php echo h(roleLabel($_SESSION['role'])); ?>)</span><a class="btn btn-info" href="system_menu.php">Menu</a><a class="btn btn-danger" href="../api/logout.php">Logout</a></div>
    </header>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>
    <section class="backup-panel">
        <div class="backup-toolbar"><div><h2>Data Backup</h2><p>Save Nursing Services and CSSR Inventory data manually or use the daily schedule. Scheduled backups also include uploaded files.</p></div></div>
    </section>
    <section class="backup-panel">
        <h3>Create Manual Backup</h3>
        <p class="backup-note">Enter your current Super Admin password. The system will generate two populated SQL files that must be kept together.</p>
        <form class="backup-form" method="post" data-confirm-message="Create a complete manual backup now?">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="manual_backup">
            <div class="form-group"><label for="admin-password">Current Administrator Password</label><input id="admin-password" type="password" name="admin_password" autocomplete="current-password" required></div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-database"></i> Create Backup Now</button>
        </form>
        <p class="password-note">Manual backups run only when you click the button above.</p>
    </section>
    <section class="backup-panel">
        <div class="backup-toolbar"><div><h3>Automatic Backup Setup</h3><p>Optional schedule managed from this module. It is not permanently forced.</p></div><span class="schedule-state <?php echo $schedule['enabled'] ? 'on' : 'off'; ?>"><?php echo $schedule['enabled'] ? 'Enabled' : 'Disabled'; ?></span></div>
        <form method="post" data-confirm-message="Save these automatic backup settings?">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_schedule">
            <div class="schedule-grid">
                <div class="form-group"><label>Automatic Backup</label><label class="schedule-toggle"><input type="checkbox" name="automatic_enabled" value="1" <?php echo $schedule['enabled'] ? 'checked' : ''; ?>> Enable daily automatic backup</label></div>
                <div class="form-group"><label for="backup-time">Backup Time</label><input id="backup-time" type="time" name="backup_time" value="<?php echo h((string) $schedule['time']); ?>" required></div>
                <div class="form-group"><label for="retention-days">Keep Backups (Days)</label><input id="retention-days" type="number" name="retention_days" min="1" max="365" value="<?php echo h((string) $schedule['retention_days']); ?>" required></div>
            </div>
            <div class="schedule-actions"><div class="form-group"><label for="schedule-password">Current Administrator Password</label><input id="schedule-password" type="password" name="admin_password" autocomplete="current-password" required></div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-clock"></i> Save Automatic Backup Settings</button></div>
        </form>
        <p class="password-note">Backup times use Asia/Singapore (UTC+08:00). The server and MySQL must be running. The scheduled service runs without an interactive Windows sign-in, catches up after missed starts, and retries failures.</p>
        <p class="backup-note"><strong>Latest run:</strong> <?php echo h($latestRun); ?> &middot; <?php echo h(['success'=>'Completed','failed'=>'Failed - server administrator action needed','running'=>'In progress'][$backupStatus['state'] ?? ''] ?? 'Not run yet'); ?></p>
        <p class="backup-note"><strong>Secondary copy:</strong> <?php echo h(!empty($secondaryCopy['enabled']) ? (string) ($secondaryCopy['destination'] ?? 'Not configured') : 'Not configured'); ?><?php if (!empty($backupStatus['secondary_verified'])): ?> &middot; Latest copy checksum verified.<?php endif; ?></p>
        <?php if (($secondaryCopy['kind'] ?? '') === 'separate_local_disk'): ?><p class="backup-note">This copy is on another disk inside the same server. An off-server backup still requires a separate computer, network storage device, or external backup device.</p><?php endif; ?>
    </section>
    <section class="backup-panel">
        <div class="backup-toolbar"><div><h3>Available Manual Backups</h3><p>Download and retain both files from the same backup date and time.</p></div></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Created</th><th>Nursing System</th><th>CSSR Inventory</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($backupSets as $stamp => $files): $complete=isset($files['nursing_system'],$files['cssr_inventory']);$createdAt=DateTime::createFromFormat('Ymd_His',$stamp); ?>
            <tr><td><?php echo h($createdAt?$createdAt->format('M d, Y h:i A'):$stamp); ?></td>
                <td><?php if(isset($files['nursing_system'])):?><a class="btn btn-sm btn-info" href="?download=<?php echo h(basename($files['nursing_system'])); ?>">Download (<?php echo h(number_format(filesize($files['nursing_system'])/1024,1)); ?> KB)</a><?php else: ?>Missing<?php endif; ?></td>
                <td><?php if(isset($files['cssr_inventory'])):?><a class="btn btn-sm btn-info" href="?download=<?php echo h(basename($files['cssr_inventory'])); ?>">Download (<?php echo h(number_format(filesize($files['cssr_inventory'])/1024,1)); ?> KB)</a><?php else: ?>Missing<?php endif; ?></td>
                <td><span class="backup-status <?php echo $complete?'complete':'incomplete'; ?>"><?php echo $complete?'Complete':'Incomplete'; ?></span></td></tr>
        <?php endforeach; ?>
        <?php if(!$backupSets): ?><tr><td class="backup-empty" colspan="4"><strong>No manual backup has been created yet.</strong><br>Enter the administrator password above and click <em>Create Backup Now</em>.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
</body></html>
