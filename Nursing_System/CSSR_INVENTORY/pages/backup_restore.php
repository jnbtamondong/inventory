<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN]);
require_once __DIR__.'/../../includes/database-backup.php';
$pageTitle = 'Backup / Restore';
$activePage = 'backup_restore';
if (isset($_POST['backup'])) {
    if (!verify_current_admin_password((string) ($_POST['admin_password'] ?? ''))) {
        log_activity('BACKUP_DENIED', 'Database', 'Invalid admin password');
        set_flash('danger', 'Admin password is required before creating a backup.');
        redirect('backup_restore.php');
    }
    $file = __DIR__ . '/../database/cssr_inventory_backup_' . date('Ymd_His') . '.sql';
    try {
        $manifest=nursingCreateDatabaseBackup(nursingBackupConfig('cssr_inventory'),$file,nursingLegacyBackupExclusions('cssr_inventory'));
        log_activity('BACKUP', 'Database', basename($file).' SHA256 '.$manifest['sha256']);
        set_flash('warning', 'Backup created: '.basename($file).'. Legacy backup tables are excluded and listed in the manifest. All active application tables are included. Uploaded files require a separate backup.');
    } catch(Throwable $error) {
        set_flash('danger',safe_error_message($error,'Backup failed. Please contact the administrator.'));
    }
    redirect('backup_restore.php');
}
if (isset($_POST['restore'])) {
    if (!verify_current_admin_password((string) ($_POST['admin_password'] ?? ''))) {
        log_activity('RESTORE_DENIED', 'Database', 'Invalid admin password');
        set_flash('danger', 'Admin password is required before restoring a backup.');
        redirect('backup_restore.php');
    }
    if (($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $name = (string) ($_FILES['sql_file']['name'] ?? '');
        $tmp = (string) ($_FILES['sql_file']['tmp_name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql' || ($_FILES['sql_file']['size'] ?? 0) > 10 * 1024 * 1024) {
            set_flash('danger', 'Only SQL backup files up to 10MB are allowed.');
            redirect('backup_restore.php');
        }
        $sql = file_get_contents($tmp);
        if ($sql === false || stripos($sql, '<?php') !== false || preg_match('/\b(system|shell_exec|exec|passthru)\s*\(/i', $sql)) {
            set_flash('danger', 'Invalid SQL backup file.');
            redirect('backup_restore.php');
        }
        try {
            db()->exec($sql);
            log_activity('RESTORE', 'Database', basename($name));
            set_flash('success', 'Database restored.');
        } catch (Throwable $e) {
            set_flash('danger', safe_error_message($e, 'Restore failed. The database error was logged.'));
        }
    } else {
        set_flash('danger', 'Please upload a SQL file.');
    }
    redirect('backup_restore.php');
}
$backups = glob(__DIR__ . '/../database/cssr_inventory_backup_*.sql') ?: [];
include __DIR__ . '/../includes/header.php';
?>
<section class="metric-row">
    <div class="metric-card"><span>Available Backups</span><strong><?= count($backups) ?></strong></div>
    <div class="metric-card"><span>Backup Contents</span><strong>Schema + Data</strong></div>
    <div class="metric-card"><span>Mode</span><strong>Local SQL</strong></div>
    <div class="metric-card"><span>Access</span><strong><?= e(current_user()['role']) ?></strong></div>
</section>
<section class="page-panel">
    <h3>Database Maintenance</h3>
    <div class="row">
        <div class="col-md-6 mb-2">
            <div class="form-section">
                <div class="form-section-title"><h4>Create Backup</h4><span>Writes SQL to database folder</span></div>
                <form method="post" data-confirm-message="Create a database backup now?" data-loading-message="Creating database backup...">
                    <?= csrf_field() ?>
                    <p class="text-muted">Back up active table definitions, data, views, and database routines. Excluded legacy backup tables are recorded in a verification manifest. Uploaded files must also be backed up separately.</p>
                    <label class="form-label required">Admin Password</label>
                    <input class="form-control mb-2" type="password" name="admin_password" required>
                    <button class="btn btn-primary" name="backup" value="1">Create Backup SQL</button>
                </form>
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <div class="form-section">
                <div class="form-section-title"><h4>Restore Database</h4><span>Use with care</span></div>
                <form method="post" enctype="multipart/form-data" data-confirm-message="Restore will execute the uploaded SQL file. Continue?" data-loading-message="Restoring database...">
                    <?= csrf_field() ?>
                    <label class="form-label required">Admin Password</label>
                    <input class="form-control mb-2" type="password" name="admin_password" required>
                    <label class="form-label required">SQL File</label>
                    <input class="form-control mb-2" type="file" name="sql_file" accept=".sql" required>
                    <button class="btn btn-warning" name="restore" value="1">Restore SQL</button>
                </form>
            </div>
        </div>
    </div>
</section>
<section class="page-panel">
    <h3>Available Backups</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>File Name</th><th>Location</th></tr></thead>
            <tbody><?php foreach ($backups as $b): ?><tr><td><strong><?= e(basename($b)) ?></strong></td><td>database/<?= e(basename($b)) ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
