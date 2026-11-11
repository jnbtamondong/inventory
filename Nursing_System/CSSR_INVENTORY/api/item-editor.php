<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Includes current-session refresh and CSRF validation for POST.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); header('Allow: POST'); echo json_encode(['ok'=>false,'errors'=>['_form'=>'Use the item form to save changes.']]); exit;
}
if (!shared_has_permission('cssr_inventory','view') || !can_manage()) {
    http_response_code(403); echo json_encode(['ok'=>false,'errors'=>['_form'=>'You do not have permission to add or edit inventory items.']]); exit;
}
require_once __DIR__ . '/../includes/item-editor.php';
try {
    $units = array_column(db_all("SELECT DISTINCT TRIM(unit) unit FROM items WHERE TRIM(COALESCE(unit,''))<>''"),'unit');
    $result = cssrSaveItemEditor(db(),$_POST,cssrItemEditorOptions($units));
    if ($result['ok']) {
        $message = 'Item ' . $result['item_code'] . ($result['created'] ? ' added successfully.' : ' updated successfully.');
        log_activity($result['created'] ? 'CREATE' : 'UPDATE','Items',$message);
        set_flash('success',$message); $result['message'] = $message;
    } else http_response_code(422);
    echo json_encode($result,JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    log_error($error,'ITEM_EDITOR'); http_response_code(500);
    echo json_encode(['ok'=>false,'errors'=>['_form'=>'The save could not be confirmed. Check inventory before trying again. Your entries are still here.']]);
}
