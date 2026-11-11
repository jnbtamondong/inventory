<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('NIS_TEST_ER_LIVE=1');require __DIR__.'/nis-registration.test.php';
