<?php
declare(strict_types=1);
function nursingThemeAssets(): string
{
    $root=__DIR__.'/../public/';
    $session=$_SESSION??[];$user=$session['user']??[];
    $name=trim((string)($user['full_name']??$session['full_name']??trim(($session['first_name']??'').' '.($session['last_name']??''))));
    $role=(string)($session['original_role']??$session['role']??$user['role']??'');
    $identity=json_encode(['name'=>$name?:($session['username']??''),'role'=>ucwords(strtolower(str_replace('_',' ',$role)))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    return '<script data-nursing-theme src="/Nursing_System/public/js/app-theme.js?v='.filemtime($root.'js/app-theme.js').'"></script>'
        .'<link rel="stylesheet" href="/Nursing_System/public/css/app-theme.css?v='.filemtime($root.'css/app-theme.css').'">'
        .'<link rel="stylesheet" href="/Nursing_System/public/css/workspace-design.css?v='.filemtime($root.'css/workspace-design.css').'">'
        .'<script type="application/json" id="ns-account-identity">'.$identity.'</script>'
        .'<link rel="stylesheet" href="/Nursing_System/public/css/account-menu.css?v='.filemtime($root.'css/account-menu.css').'">'
        .'<script defer src="/Nursing_System/public/js/account-menu.js?v='.filemtime($root.'js/account-menu.js').'"></script>'
        .'<link rel="stylesheet" href="/Nursing_System/public/css/sidebar-presentation.css?v='.filemtime($root.'css/sidebar-presentation.css').'">'
        .'<script defer src="/Nursing_System/public/js/sidebar-presentation.js?v='.filemtime($root.'js/sidebar-presentation.js').'"></script>';
}
function nursingInjectThemeAssets(string $html): string
{
    if(str_contains($html,'data-nursing-theme src=')||!preg_match('/<head\b[^>]*>/i',$html))return $html;
    // Authenticated HTML only. API/CSV output has no head and is unchanged.
    return preg_replace_callback('/<head\b[^>]*>/i',static fn($match)=>$match[0].nursingThemeAssets(),$html,1);
}
