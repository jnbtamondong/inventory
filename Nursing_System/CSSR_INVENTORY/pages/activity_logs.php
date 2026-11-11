<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN]);
$pageTitle = 'Activity Logs';
$activePage = 'activity_logs';
$rows = db_all('SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 500');
include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="module-actions"><button class="btn btn-light" onclick="printPage()">Print Audit Trail</button></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['created_at']) ?></td>
                    <td><strong><?= e($r['full_name'] ?? 'System') ?></strong></td>
                    <td><span class="badge bg-secondary"><?= e($r['action']) ?></span></td>
                    <td><?= e($r['module']) ?></td>
                    <td><?= e($r['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
