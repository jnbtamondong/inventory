<?php
// Dedicated display-only endpoint: intentionally does not load staff authentication/session APIs.
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/er-live-tracker.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo '{"error":"Read-only display."}';exit;}
if($_GET){http_response_code(400);echo '{"error":"Individual lookup is not supported."}';exit;}
try{
    $token=is_string($_COOKIE['er_kiosk']??null)?$_COOKIE['er_kiosk']:'';
    if(!erTrackerReady($conn)||!erAuthorizeDevice($conn,$token,true)){http_response_code(401);echo '{"error":"Display authorization required."}';exit;}
    echo json_encode(erPublicDataset($conn),JSON_THROW_ON_ERROR);
}catch(DomainException $e){http_response_code(429);header('Retry-After: 2');echo '{"error":"Please wait before refreshing."}';}
catch(Throwable $e){http_response_code(503);error_log('ER public display failed; code '.(int)$e->getCode());echo '{"error":"Display temporarily unavailable."}';}
