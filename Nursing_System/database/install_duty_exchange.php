<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='CLI';
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/duty_exchange.php';
exSchema($conn);echo "Exchange of Duty schema installed. No published roster data was changed.\n";
