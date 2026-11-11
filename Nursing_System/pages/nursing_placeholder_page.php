<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
$pageTitle = $pageTitle ?? 'Nursing Module';
$pageDescription = $pageDescription ?? 'This nursing workflow page is ready for encoding once the detailed form is finalized.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div></nav>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <section class="page-header">
            <h2><?php echo h($pageTitle); ?></h2>
            <p class="compact-meta"><?php echo h($pageDescription); ?></p>
        </section>
        <section class="patients-section">
            <div class="empty-state">No records have been encoded yet.</div>
        </section>
    </main>
</div>
<script src="../public/js/sidebar.js"></script>
</body>
</html>
