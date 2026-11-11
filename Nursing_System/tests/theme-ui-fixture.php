<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/theme-presentation.php';
$fixture=$argv[1]??'menu';
if(!in_array($fixture,['menu','admin','procurement','schedule','employee','dashboard','reports','backup','settings'],true))exit(1);
ob_start();
if(in_array($fixture,['dashboard','reports','backup','settings'],true))require __DIR__.'/static-theme-fixture.php';
else require __DIR__.'/'.($fixture==='schedule'?'schedule-fixture.php':($fixture==='employee'?'employee-theme-fixture.php':$fixture.'-ui-fixture.php'));
echo nursingInjectThemeAssets(ob_get_clean());
