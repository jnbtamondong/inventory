<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['REQUEST_METHOD']='CLI';require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/nursing_requests.php';
$before=nsScheduleQuery($conn,"SELECT id,version,final_json FROM nursing_schedule_months ORDER BY id")->get_result()->fetch_all(MYSQLI_ASSOC);nrSchema($conn);$after=nsScheduleQuery($conn,"SELECT id,version,final_json FROM nursing_schedule_months ORDER BY id")->get_result()->fetch_all(MYSQLI_ASSOC);if(nsScheduleEncode($before)!==nsScheduleEncode($after))throw new RuntimeException('Schedule snapshots unexpectedly changed.');
echo "Nursing Scheduling, Request-Off and Leave schemas installed. Existing roster versions and published snapshots unchanged.\n";
