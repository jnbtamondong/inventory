<?php
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';requireLogin();require_once __DIR__.'/../includes/duty_exchange.php';
exSchema($conn);$actor=exActor($conn,(int)$_SESSION['user_id']);$access=exAccess($conn,$actor);if(!$access['view']){http_response_code(403);exit('Exchange of Duty is unavailable for this account.');}
function exh($value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$view=(string)($_GET['view']??'new');if(!in_array($view,['new','configuration'],true))$view='new';
if(($view==='new'&&!$access['submit'])||($view==='configuration'&&!$access['admin'])||($view==='approvals'&&!naApprovalAccess($actor))){http_response_code(403);exit('Access denied.');}
$employees=[];foreach(exRows($conn,"SELECT * FROM users WHERE status='ACTIVE' AND is_active=1 ORDER BY last_name,first_name") as $employee){$employee['canonical_role']=canonicalRole($employee['role']);$employee['unit']=canonicalDepartment($employee['department']);if(naEligible($employee))$employees[]=['id'=>(int)$employee['user_id'],'name'=>naName($employee),'employee_id'=>$employee['employee_id']??$employee['user_id'],'department'=>$employee['unit'],'role'=>naRole($employee)];}
$boot=['view'=>$view,'id'=>(int)($_GET['id']??0),'actor'=>['id'=>(int)$actor['user_id'],'name'=>$actor['name'],'department'=>$actor['unit'],'employee_id'=>$actor['employee_id']??$actor['user_id'],'role'=>$actor['canonical_role']],'access'=>$access,'employees'=>$employees,'csrf'=>csrfToken(),'submission_key'=>bin2hex(random_bytes(24))];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Exchange of Duty | JVGH</title>
<?=nursingThemeAssets()?><link rel="stylesheet" href="../public/css/schedule-management.css"><link rel="stylesheet" href="../public/css/request-tracking.css?v=2"><link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/exchange-application.css?v=<?=filemtime(__DIR__.'/../public/css/exchange-application.css')?>">
<script defer src="../public/js/sidebar.js"></script><script defer src="../public/js/nursync-ui.js?v=4"></script><script defer src="../public/js/exchange-application.js?v=<?=filemtime(__DIR__.'/../public/js/exchange-application.js')?>"></script></head>
<body class="module-ui schedule-page request-tracking-page exchange-application-page"><?php include __DIR__.'/../includes/schedule_sidebar.php'; ?>
<nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>NURSYNC</h1><span class="navbar-system-subtitle">Exchange of Duty</span></div></div><div class="navbar-menu"><span class="user-info"><?=exh($actor['name'])?> (<?=exh(roleLabel($actor['role']))?>)</span></div></nav>
<main class="main-content schedule-main"><header class="schedule-heading"><div><p class="schedule-eyebrow">Nursing scheduling</p><h2><?=$view==='configuration'?'Exchange settings':'Exchange of Duty'?></h2><p>Request, confirm, and review published duty swaps.</p></div><a class="btn btn-light" href="my_requests.php?type=EXCHANGE">My Requests</a></header>
<p id="exchangeMessage" class="exchange-message" role="status" hidden></p>
<section id="exchangeContent" aria-live="polite"><p>Loading exchange requests…</p></section>
<script id="exchangeBoot" type="application/json"><?=json_encode($boot,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?></script>
</main></body></html>
