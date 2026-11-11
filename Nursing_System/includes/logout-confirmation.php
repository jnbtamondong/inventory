<?php
declare(strict_types=1);

// GET remains a working URL, but cannot change authentication state.
function nursingRequireLogoutPost(string $token): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $submitted = $_POST['csrf_token'] ?? '';
        if (!is_string($submitted) || $token === '' || !hash_equals($token, $submitted)) {
            http_response_code(419);
            exit('Security token expired. Refresh the page before signing out.');
        }
        return;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        header('Allow: GET, HEAD, POST');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $field = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign out</title><link rel="stylesheet" href="/Nursing_System/public/css/app-theme.css?v='.filemtime(__DIR__.'/../public/css/app-theme.css').'">
    <style>body{margin:0;min-height:100dvh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;background:#f3f7fa;color:#193746}main{box-sizing:border-box;width:min(440px,calc(100% - 32px));padding:28px;border:1px solid #ccd9e2;border-radius:16px;background:#fff}h1{font-size:24px;margin:0 0 12px}.actions{display:flex;flex-wrap:wrap;gap:12px}button,a{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border:1px solid #bdcdd8;border-radius:8px;font:inherit;text-decoration:none}button{background:#087f86;color:white;cursor:pointer}a{background:#eff4f8;color:#193746}html[data-theme=dark] body{background:#101c29;color:#e5edf5}html[data-theme=dark] main{background:#1b2d3d;border-color:#385366}html[data-theme=dark] a{background:#233b4f;color:#e5edf5}</style>
    <script>try{if((localStorage.getItem("jvgh.theme")??localStorage.getItem("jvgh.cssr.theme"))==="dark")document.documentElement.dataset.theme="dark"}catch(e){}</script></head><body class="logout-page"><main><h1>Sign out?</h1><p>You are about to sign out of NURSYNC. Sign in again to return to your workspace.</p><form method="post"><input type="hidden" name="csrf_token" value="'.$field.'"><div class="actions"><a href="/Nursing_System/pages/system_menu.php">Cancel</a><button type="submit">Sign out</button></div></form></main></body></html>';
    exit;
}
