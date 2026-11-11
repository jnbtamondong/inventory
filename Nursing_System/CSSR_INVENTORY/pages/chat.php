<?php
require_once __DIR__ . '/../includes/auth.php';
$lastChatInteraction = $_SESSION['last_activity'] ?? time();
require_login();
if (($_POST['chat_action'] ?? '') === 'fetch') $_SESSION['last_activity'] = $lastChatInteraction;

$pageTitle = 'CSSR Chat';
$activePage = 'chat';
$currentUserId = (int) ($_SESSION['user_id'] ?? (current_user()['shared_user_id'] ?? 0));

function chat_users(int $currentUserId): array
{
    $isCssrUser = can_manage() || is_cssr_department();
    $scopeSql = $isCssrUser
        ? ''
        : " AND (role IN ('SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF') OR department='CSSR')";
    return shared_statement(
        "SELECT user_id, username, COALESCE(NULLIF(full_name,''), CONCAT_WS(' ',first_name,last_name), username) AS display_name,
                role, department
         FROM users
         WHERE status='ACTIVE' AND COALESCE(is_active,1)=1 AND user_id<>? {$scopeSql}
         ORDER BY display_name, username",
        [$currentUserId]
    )->fetchAll();
}

function chat_user_exists(int $userId): bool
{
    if (can_manage() || is_cssr_department()) {
        return (bool) shared_value("SELECT 1 FROM users WHERE user_id=? AND status='ACTIVE' AND COALESCE(is_active,1)=1", [$userId]);
    }
    return (bool) shared_value(
        "SELECT 1 FROM users WHERE user_id=? AND status='ACTIVE' AND COALESCE(is_active,1)=1
         AND (role IN ('SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF') OR department='CSSR')",
        [$userId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        verify_csrf_token();
        $action = (string) $_POST['chat_action'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        if ($contactId <= 0 || $contactId === $currentUserId || !chat_user_exists($contactId)) {
            throw new RuntimeException('Select a valid staff account.');
        }

        if ($action === 'send') {
            $message = trim((string) ($_POST['message'] ?? ''));
            if (mb_strlen($message) > 1000) {
                throw new RuntimeException('Message must not exceed 1,000 characters.');
            }
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentMime = null;
            $upload = $_FILES['attachment'] ?? null;
            if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The picture could not be uploaded.');
                }
                if ((int) $upload['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('Picture size must not exceed 5 MB.');
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file((string) $upload['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime])) {
                    throw new RuntimeException('Only JPG, PNG, and WebP pictures are allowed.');
                }
                $uploadDirectory = __DIR__ . '/../uploads/chat';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('The chat upload folder is unavailable.');
                }
                $fileName = bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
                if (!move_uploaded_file((string) $upload['tmp_name'], $uploadDirectory . '/' . $fileName)) {
                    throw new RuntimeException('The picture could not be saved.');
                }
                $attachmentPath = $fileName;
                $attachmentName = mb_substr(basename((string) $upload['name']), 0, 255);
                $attachmentMime = $mime;
            }
            if ($message === '' && $attachmentPath === null) {
                throw new RuntimeException('Enter a message or attach a picture.');
            }
            db_statement(
                'INSERT INTO chat_messages(sender_user_id,receiver_user_id,message_text,attachment_path,attachment_name,attachment_mime) VALUES(?,?,?,?,?,?)',
                [$currentUserId, $contactId, $message, $attachmentPath, $attachmentName, $attachmentMime]
            );
        } elseif ($action !== 'fetch') {
            throw new RuntimeException('Unsupported chat action.');
        }

        db_statement('UPDATE chat_messages SET read_at=NOW() WHERE sender_user_id=? AND receiver_user_id=? AND read_at IS NULL', [$contactId, $currentUserId]);
        $afterId = max(0, (int) ($_POST['after_id'] ?? 0));
        $messages = db_all(
            'SELECT id,sender_user_id,receiver_user_id,message_text,sent_at,read_at,attachment_path,attachment_name,attachment_mime
             FROM chat_messages
             WHERE ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) AND id>?
             ORDER BY id ASC LIMIT 200',
            [$currentUserId, $contactId, $contactId, $currentUserId, $afterId]
        );
        $seenThrough = (int) db_value(
            'SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE sender_user_id=? AND receiver_user_id=? AND read_at IS NOT NULL',
            [$currentUserId, $contactId]
        );
        $unreadTotal = (int) db_value('SELECT COUNT(*) FROM chat_messages WHERE receiver_user_id=? AND read_at IS NULL', [$currentUserId]);
        echo json_encode(['ok' => true, 'messages' => $messages, 'seen_through' => $seenThrough, 'unread_total' => $unreadTotal], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => safe_error_message($e)], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$users = chat_users($currentUserId);
$selectedUserId = (int) ($_GET['user'] ?? 0);
$unreadRows = db_all('SELECT sender_user_id,COUNT(*) AS total FROM chat_messages WHERE receiver_user_id=? AND read_at IS NULL GROUP BY sender_user_id', [$currentUserId]);
$unread = [];
foreach ($unreadRows as $row) {
    $unread[(int) $row['sender_user_id']] = (int) $row['total'];
}
$latestRows = db_all(
    'SELECT m.sender_user_id,m.receiver_user_id,m.message_text,m.attachment_path
     FROM chat_messages m
     INNER JOIN (
        SELECT CASE WHEN sender_user_id=? THEN receiver_user_id ELSE sender_user_id END AS contact_id, MAX(id) AS latest_id
        FROM chat_messages
        WHERE sender_user_id=? OR receiver_user_id=?
        GROUP BY contact_id
     ) latest ON latest.latest_id=m.id',
    [$currentUserId, $currentUserId, $currentUserId]
);
$latestMessages = [];
foreach ($latestRows as $row) {
    $contactId = (int) $row['sender_user_id'] === $currentUserId ? (int) $row['receiver_user_id'] : (int) $row['sender_user_id'];
    $preview = trim((string) ($row['message_text'] ?? ''));
    if ($preview === '' && !empty($row['attachment_path'])) {
        $preview = 'Sent a picture';
    }
    $latestMessages[$contactId] = ['text' => $preview, 'mine' => (int) $row['sender_user_id'] === $currentUserId];
}
$selectedUser = null;
foreach ($users as $user) {
    if ((int) $user['user_id'] === $selectedUserId) {
        $selectedUser = $user;
        break;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="chat-shell page-panel <?= $selectedUser ? 'has-conversation' : 'contact-picker' ?>" data-current-user="<?= $currentUserId ?>" data-contact-user="<?= (int) ($selectedUser['user_id'] ?? 0) ?>">
    <aside class="chat-contacts" aria-label="Chat contacts">
        <div class="chat-contacts-head">
            <span class="eyebrow">Supply Concerns</span>
            <h3>CSSR Conversations</h3>
            <input class="form-control" id="chatContactSearch" type="search" placeholder="Search staff">
        </div>
        <div class="chat-contact-list" id="chatContactList">
            <?php foreach ($users as $user): ?>
                <a class="chat-contact <?= (int) $user['user_id'] === $selectedUserId ? 'active' : '' ?>" href="chat.php?user=<?= (int) $user['user_id'] ?>" data-contact-name="<?= e(strtolower(($user['display_name'] ?? '') . ' ' . ($user['department'] ?? '') . ' ' . ($user['role'] ?? ''))) ?>">
                    <span class="chat-avatar"><?= e(strtoupper(substr((string) ($user['display_name'] ?: $user['username']), 0, 1))) ?></span>
                    <span><strong><?= e($user['display_name'] ?: $user['username']) ?></strong><small><?= e(shared_department_label($user['department'] ?? '')) ?> · <?= e(str_replace('_', ' ', (string) $user['role'])) ?></small></span>
                    <?php if (!empty($unread[(int) $user['user_id']])): ?><b class="chat-unread"><?= (int) $unread[(int) $user['user_id']] ?></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$users): ?><div class="pos-empty">No other active staff accounts found.</div><?php endif; ?>
        </div>
        <script>
        (function(){
            const latest=<?= json_encode($latestMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            document.querySelectorAll('.chat-contact').forEach(contact=>{
                const userId=new URL(contact.href,window.location.href).searchParams.get('user');
                const message=latest[userId];
                if(!message)return;
                const preview=contact.querySelector('small');
                if(preview)preview.textContent=(message.mine?'You: ':'')+message.text;
            });
        })();
        </script>
    </aside>

    <div class="chat-conversation">
        <?php if ($selectedUser): ?>
            <header class="chat-conversation-head">
                <a class="btn btn-light chat-mobile-back" href="chat.php" aria-label="Back to CSSR contacts">←</a>
                <span class="chat-avatar"><?= e(strtoupper(substr((string) ($selectedUser['display_name'] ?: $selectedUser['username']), 0, 1))) ?></span>
                <div><strong><?= e($selectedUser['display_name'] ?: $selectedUser['username']) ?></strong><small><?= e(shared_department_label($selectedUser['department'] ?? '')) ?> · <?= e(str_replace('_', ' ', (string) $selectedUser['role'])) ?></small></div>
            </header>
            <div class="chat-messages" id="chatMessages" aria-live="polite"><div class="chat-loading">Loading conversation…</div></div>
            <form class="chat-compose" id="chatForm">
                <input type="hidden" id="chatCsrf" value="<?= e(csrf_token()) ?>">
                <label class="chat-circle-button chat-attach" title="Attach item picture" aria-label="Attach item picture"><input id="chatAttachment" type="file" accept="image/jpeg,image/png,image/webp"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.6-9.6a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 0 1-2.8-2.8l8.9-8.9"/></svg></label>
                <textarea class="form-control" id="chatMessage" maxlength="1000" rows="2" placeholder="Write a message…"></textarea>
                <button class="chat-circle-button chat-send" type="submit" title="Send message" aria-label="Send message"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button>
            </form>
        <?php else: ?>
            <div class="chat-empty"><strong>No conversation selected</strong><span>Select an active staff account to begin.</span></div>
        <?php endif; ?>
    </div>
</section>
<?php if ($selectedUser): ?>
<div class="chat-image-viewer" id="chatImageViewer" hidden role="dialog" aria-modal="true" aria-label="Picture preview">
    <a class="chat-image-viewer-download" id="chatImageDownload" href="#" download title="Download picture" aria-label="Download picture"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 20h16"/></svg></a>
    <button class="chat-image-viewer-close" type="button" aria-label="Close picture preview">&times;</button>
    <img id="chatImagePreview" src="" alt="Sent picture preview">
</div>
<script>
(function(){
    const shell=document.querySelector('.chat-shell');
    const messages=document.getElementById('chatMessages');
    const form=document.getElementById('chatForm');
    const input=document.getElementById('chatMessage');
    const currentUser=Number(shell.dataset.currentUser);
    const contactUser=Number(shell.dataset.contactUser);
    let lastId=0;
    let firstLoad=true;
    let fetching=false,sending=false;
    const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    function addMessages(rows){
        if(firstLoad){messages.innerHTML='';firstLoad=false;}
        rows.forEach(row=>{
            if(document.getElementById('chat-message-'+row.id))return;
            const mine=Number(row.sender_user_id)===currentUser;
            const article=document.createElement('article');
            article.id='chat-message-'+row.id;article.dataset.messageId=String(row.id);article.dataset.mine=mine?'1':'0';
            article.className='chat-message '+(mine?'mine':'theirs');
            const picture=row.attachment_path?'<button class="chat-picture" type="button" aria-label="View sent picture"><img src="../api/chat-attachment.php?file='+encodeURIComponent(row.attachment_path)+'" alt="'+esc(row.attachment_name||'Attached picture')+'"></button>':'';
            const text=row.message_text?'<p>'+esc(row.message_text).replace(/\n/g,'<br>')+'</p>':'';
            const sentDate=new Date(String(row.sent_at).replace(' ','T'));
            const sentLabel=Number.isNaN(sentDate.getTime())?String(row.sent_at):sentDate.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
            article.innerHTML=picture+text+'<time>'+esc(sentLabel)+'</time>';
            messages.appendChild(article);lastId=Math.max(lastId,Number(row.id));
        });
        if(rows.length||lastId===0)messages.scrollTop=messages.scrollHeight;
    }
    function updateSeen(seenThrough){
        messages.querySelectorAll('.chat-seen').forEach(label=>label.remove());
        if(!seenThrough)return;
        const seenMessage=document.getElementById('chat-message-'+seenThrough);
        if(seenMessage&&seenMessage.dataset.mine==='1'){
            const label=document.createElement('span');label.className='chat-seen';label.textContent='Seen';seenMessage.appendChild(label);
        }
    }
    function updateUnread(total){
        document.dispatchEvent(new CustomEvent('cssr:chat-unread',{detail:{total}}));
    }
    async function request(action,message,attachment){
        if(action==='fetch'&&fetching)return;
        if(action==='fetch')fetching=true;
        const body=new FormData();body.set('chat_action',action);body.set('contact_id',String(contactUser));body.set('after_id',String(lastId));body.set('csrf_token',document.getElementById('chatCsrf').value);
        if(message)body.set('message',message);if(attachment)body.set('attachment',attachment);
        const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),15000);
        try{
            const response=await fetch('chat.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body,signal:controller.signal});
            if(response.redirected)throw new Error('Your session expired. Refresh the page and sign in again.');
            const data=await response.json();if(!data.ok)throw new Error(data.message||'Chat request failed.');addMessages(data.messages||[]);updateSeen(Number(data.seen_through||0));updateUnread(Number(data.unread_total||0));
        }finally{clearTimeout(timeout);if(action==='fetch')fetching=false;}
    }
    form.addEventListener('submit',async event=>{event.preventDefault();if(sending)return;const message=input.value.trim();const file=document.getElementById('chatAttachment').files[0];if(!message&&!file)return;sending=true;input.disabled=true;const send=form.querySelector('[type=submit]');send.disabled=true;send.setAttribute('aria-label','Sending message');try{await request('send',message,file);input.value='';document.getElementById('chatAttachment').value='';}catch(error){showToastMessage('danger',error.name==='AbortError'?'Sending could not be confirmed. Check the conversation before retrying.':error.message);}finally{sending=false;send.disabled=false;send.setAttribute('aria-label','Send message');input.disabled=false;input.focus();}});
    document.getElementById('chatAttachment').addEventListener('change',function(){const button=this.closest('.chat-attach');button.classList.toggle('has-file',this.files.length>0);button.title=this.files[0]?this.files[0].name:'Attach item picture';});
    const imageViewer=document.getElementById('chatImageViewer');
    const imagePreview=document.getElementById('chatImagePreview');
    const imageDownload=document.getElementById('chatImageDownload');
    function closeImageViewer(){imageViewer.hidden=true;imagePreview.src='';imageDownload.href='#';document.body.classList.remove('chat-preview-open');}
    messages.addEventListener('click',event=>{
        const picture=event.target.closest('.chat-picture');
        if(!picture)return;
        const image=picture.querySelector('img');
        if(!image)return;
        imagePreview.src=image.src;imagePreview.alt=image.alt||'Sent picture preview';imageDownload.href=image.src;imageDownload.setAttribute('download',image.alt||'sent-picture');imageViewer.hidden=false;document.body.classList.add('chat-preview-open');
    });
    imageViewer.addEventListener('click',event=>{if(event.target===imageViewer||event.target.closest('.chat-image-viewer-close'))closeImageViewer();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!imageViewer.hidden)closeImageViewer();});
    input.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();form.requestSubmit();}});
    request('fetch').catch(error=>{messages.innerHTML='<div class="pos-empty">'+esc(error.message)+'</div>';});
    window.setInterval(()=>{if(!document.hidden)request('fetch').catch(()=>{});},2000);
})();
</script>
<?php endif; ?>
<script>
document.getElementById('chatContactSearch')?.addEventListener('input',function(){const query=this.value.toLowerCase().trim();document.querySelectorAll('.chat-contact').forEach(contact=>{contact.hidden=!contact.dataset.contactName.includes(query);});});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
