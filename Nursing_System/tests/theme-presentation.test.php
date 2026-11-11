<?php
require_once __DIR__.'/../includes/theme-presentation.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
foreach(['<!doctype html><html><head></head><body>Read only</body></html>','<html><HEAD class="test"><title>Form</title></HEAD><body><form method="post"></form></body></html>'] as $html){
    $result=nursingInjectThemeAssets($html);
    check(str_contains($result,'app-theme.js'),'Controller inserted');
    check(str_contains($result,'app-theme.css'),'Styles inserted');
    check(nursingInjectThemeAssets($result)===$result,'Assets inserted once');
    check(strpos($result,'data-nursing-theme')<strpos($result,'<body>'),'Preference applied before body');
}
foreach(['{"success":true}','column,value',''] as $raw)check(nursingInjectThemeAssets($raw)===$raw,'Non-HTML response unchanged');
echo "Passed theme injection for read-only pages, forms, duplicate assets and non-HTML responses.\n";
