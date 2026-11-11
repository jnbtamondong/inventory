<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/workflow-presentation.php';
require_once __DIR__.'/../../includes/theme-presentation.php';
require_once __DIR__ . '/../../CSSR_INVENTORY/includes/auth.php';
require_once __DIR__ . '/../../includes/procurement-access.php';
require_login();
if (!canAccessProcurementWorkspace(current_user() ?? [])) {
    http_response_code(403);
    exit('Access denied. Procurement is restricted to CSSR and authorized nursing management roles.');
}

function can_approve_ppmp(): bool
{
    return in_array(current_user()['role'] ?? '', [ROLE_ADMIN, ROLE_SUPERVISOR_NURSE, ROLE_CHIEF_NURSE], true);
}

function can_review_procurement_item_requests(): bool
{
    return in_array(current_user()['role'] ?? '', [ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF, ROLE_SUPERVISOR_NURSE, ROLE_CHIEF_NURSE, ROLE_HEAD_NURSE], true);
}

function ensure_procurement_schema(): void
{
    static $done=false;if($done)return;$done=true;
    $queries=[
        'CREATE TABLE IF NOT EXISTS procurement_ppmp (id INT AUTO_INCREMENT PRIMARY KEY,ppmp_no VARCHAR(40) NOT NULL UNIQUE,fiscal_year SMALLINT NOT NULL,plan_type VARCHAR(10) NULL,department_id INT NULL,fund_source VARCHAR(120) NULL,project_type VARCHAR(40) NULL,procurement_mode VARCHAR(100) NULL,pre_procurement_conference VARCHAR(3) NULL,procurement_start_date DATE NULL,procurement_end_date DATE NULL,expected_delivery_date DATE NULL,purpose TEXT NULL,supporting_documents TEXT NULL,remarks TEXT NULL,status VARCHAR(30) NOT NULL DEFAULT "DRAFT",prepared_by VARCHAR(150) NOT NULL,approved_by VARCHAR(150) NULL,approved_at DATETIME NULL,created_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_ppmp_year_status(fiscal_year,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_ppmp_items (id INT AUTO_INCREMENT PRIMARY KEY,ppmp_id INT NOT NULL,item_id INT NULL,item_request_id INT NULL,unit VARCHAR(50) NULL,item_description TEXT NOT NULL,planned_quantity INT NOT NULL,unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,procurement_schedule VARCHAR(80) NULL,remarks VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(ppmp_id) REFERENCES procurement_ppmp(id) ON DELETE CASCADE,FOREIGN KEY(item_id) REFERENCES items(id),UNIQUE KEY uq_ppmp_item(ppmp_id,item_id),UNIQUE KEY uq_ppmp_item_request(ppmp_id,item_request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_ppmp_review_comments (id INT AUTO_INCREMENT PRIMARY KEY,ppmp_id INT NOT NULL,ppmp_item_id INT NOT NULL,review_field VARCHAR(30) NOT NULL DEFAULT "GENERAL",comment TEXT NOT NULL,follow_up_comments LONGTEXT NULL,resolution_status VARCHAR(30) NOT NULL DEFAULT "PENDING REVISION",reviewed_by VARCHAR(150) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(ppmp_id) REFERENCES procurement_ppmp(id) ON DELETE CASCADE,FOREIGN KEY(ppmp_item_id) REFERENCES procurement_ppmp_items(id) ON DELETE CASCADE,UNIQUE KEY uq_ppmp_review_field(ppmp_id,ppmp_item_id,review_field)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_ppmp_general_comments (id INT AUTO_INCREMENT PRIMARY KEY,ppmp_id INT NOT NULL UNIQUE,comment TEXT NOT NULL,follow_up_comments LONGTEXT NULL,resolution_status VARCHAR(30) NOT NULL DEFAULT "PENDING REVISION",reviewed_by VARCHAR(150) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(ppmp_id) REFERENCES procurement_ppmp(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_ppmp_revision_baselines (ppmp_id INT NOT NULL,item_id INT NOT NULL,item_description TEXT NOT NULL,unit VARCHAR(50) NULL,planned_quantity INT NOT NULL,unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,PRIMARY KEY(ppmp_id,item_id),FOREIGN KEY(ppmp_id) REFERENCES procurement_ppmp(id) ON DELETE CASCADE,FOREIGN KEY(item_id) REFERENCES items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_item_requests (id INT AUTO_INCREMENT PRIMARY KEY,item_description VARCHAR(255) NOT NULL,unit VARCHAR(50) NOT NULL,category VARCHAR(120) NOT NULL,requested_quantity INT NOT NULL DEFAULT 1,estimated_unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,justification TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT "PENDING",requested_by INT NULL,department_id INT NULL,reviewed_by INT NULL,review_remarks TEXT NULL,approved_item_id INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,INDEX idx_item_request_status(status),FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL,FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(approved_item_id) REFERENCES items(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_requests (id INT AUTO_INCREMENT PRIMARY KEY,pr_no VARCHAR(40) NOT NULL UNIQUE,ppmp_id INT NOT NULL,request_date DATE NOT NULL,department_id INT NULL,fund_cluster VARCHAR(100) NULL,responsibility_center VARCHAR(100) NULL,charged_to VARCHAR(150) NULL,purpose TEXT NULL,status VARCHAR(30) NOT NULL DEFAULT "AWAITING DELIVERY",requested_by VARCHAR(150) NOT NULL,approved_by VARCHAR(150) NULL,approved_at DATETIME NULL,expected_delivery_date DATE NULL,supplier_id INT NULL,po_number VARCHAR(80) NULL,created_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(ppmp_id) REFERENCES procurement_ppmp(id),INDEX idx_pr_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_request_items (id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,ppmp_item_id INT NOT NULL,item_id INT NOT NULL,unit VARCHAR(50) NULL,item_description TEXT NOT NULL,quantity_requested INT NOT NULL,unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,quantity_delivered INT NOT NULL DEFAULT 0,remarks VARCHAR(255) NULL,FOREIGN KEY(request_id) REFERENCES procurement_requests(id) ON DELETE CASCADE,FOREIGN KEY(ppmp_item_id) REFERENCES procurement_ppmp_items(id),FOREIGN KEY(item_id) REFERENCES items(id),UNIQUE KEY uq_pr_ppmp_item(request_id,ppmp_item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_deliveries (id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,delivery_no VARCHAR(60) NOT NULL UNIQUE,delivery_date DATE NOT NULL,delivery_receipt_no VARCHAR(100) NULL,invoice_no VARCHAR(100) NULL,supplier_name VARCHAR(150) NULL,status VARCHAR(30) NOT NULL DEFAULT "FOR INSPECTION",remarks TEXT NULL,recorded_by VARCHAR(150) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(request_id) REFERENCES procurement_requests(id),INDEX idx_delivery_request(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS procurement_delivery_items (id INT AUTO_INCREMENT PRIMARY KEY,delivery_id INT NOT NULL,request_item_id INT NOT NULL,quantity_received INT NOT NULL,quantity_accepted INT NOT NULL DEFAULT 0,quantity_rejected INT NOT NULL DEFAULT 0,lot_number VARCHAR(120) NULL,expiry_date DATE NULL,remarks VARCHAR(255) NULL,FOREIGN KEY(delivery_id) REFERENCES procurement_deliveries(id) ON DELETE CASCADE,FOREIGN KEY(request_item_id) REFERENCES procurement_request_items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    ];
    foreach($queries as$sql)db_statement($sql);
    $ppmpItemColumns=db_all('SHOW COLUMNS FROM procurement_ppmp_items');$ppmpItemColumnMap=array_column($ppmpItemColumns,null,'Field');
    if(($ppmpItemColumnMap['item_id']['Null']??'NO')!=='YES')db_statement('ALTER TABLE procurement_ppmp_items MODIFY item_id INT NULL');
    if(!isset($ppmpItemColumnMap['item_request_id']))db_statement('ALTER TABLE procurement_ppmp_items ADD COLUMN item_request_id INT NULL AFTER item_id,ADD UNIQUE KEY uq_ppmp_item_request(ppmp_id,item_request_id),ADD CONSTRAINT fk_ppmp_item_request FOREIGN KEY(item_request_id) REFERENCES procurement_item_requests(id) ON DELETE SET NULL');
    elseif(!(int)db_value('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME="procurement_ppmp_items" AND CONSTRAINT_NAME="fk_ppmp_item_request"'))db_statement('ALTER TABLE procurement_ppmp_items ADD CONSTRAINT fk_ppmp_item_request FOREIGN KEY(item_request_id) REFERENCES procurement_item_requests(id) ON DELETE SET NULL');
    $ppmpColumns=array_column(db_all('SHOW COLUMNS FROM procurement_ppmp'),'Field');
    if(!in_array('project_type',$ppmpColumns,true))db_statement('ALTER TABLE procurement_ppmp ADD COLUMN project_type VARCHAR(40) NULL AFTER fund_source');
    if(!in_array('procurement_mode',$ppmpColumns,true))db_statement('ALTER TABLE procurement_ppmp ADD COLUMN procurement_mode VARCHAR(100) NULL AFTER project_type');
    foreach(['plan_type'=>'VARCHAR(10)','pre_procurement_conference'=>'VARCHAR(3)','procurement_start_date'=>'DATE','procurement_end_date'=>'DATE','expected_delivery_date'=>'DATE','supporting_documents'=>'TEXT','remarks'=>'TEXT','review_comment'=>'TEXT','reference_budget'=>'DECIMAL(14,2)'] as$column=>$definition){if(!in_array($column,$ppmpColumns,true))db_statement("ALTER TABLE procurement_ppmp ADD COLUMN $column $definition NULL");}
    if(!in_array('revision_round',$ppmpColumns,true))db_statement('ALTER TABLE procurement_ppmp ADD COLUMN revision_round INT NOT NULL DEFAULT 0');
    $commentColumns=array_column(db_all('SHOW COLUMNS FROM procurement_ppmp_review_comments'),'Field');
    if(!in_array('review_field',$commentColumns,true))db_statement('ALTER TABLE procurement_ppmp_review_comments ADD COLUMN review_field VARCHAR(30) NOT NULL DEFAULT "GENERAL" AFTER ppmp_item_id');
    if(!in_array('resolution_status',$commentColumns,true))db_statement('ALTER TABLE procurement_ppmp_review_comments ADD COLUMN resolution_status VARCHAR(30) NOT NULL DEFAULT "PENDING REVISION" AFTER comment');
    if(!in_array('follow_up_comments',$commentColumns,true))db_statement('ALTER TABLE procurement_ppmp_review_comments ADD COLUMN follow_up_comments LONGTEXT NULL AFTER comment');
    $commentIndexes=db_all('SHOW INDEX FROM procurement_ppmp_review_comments');$indexNames=array_unique(array_column($commentIndexes,'Key_name'));
    if(in_array('uq_ppmp_review_item',$indexNames,true))db_statement('ALTER TABLE procurement_ppmp_review_comments DROP INDEX uq_ppmp_review_item');
    if(!in_array('uq_ppmp_review_field',$indexNames,true))db_statement('ALTER TABLE procurement_ppmp_review_comments ADD UNIQUE KEY uq_ppmp_review_field(ppmp_id,ppmp_item_id,review_field)');
    $baselineColumns=array_column(db_all('SHOW COLUMNS FROM procurement_ppmp_revision_baselines'),'Field');if(!in_array('unit',$baselineColumns,true))db_statement('ALTER TABLE procurement_ppmp_revision_baselines ADD COLUMN unit VARCHAR(50) NULL AFTER item_description');
    $requestItemColumns=array_column(db_all('SHOW COLUMNS FROM procurement_request_items'),'Field');
    $requestColumns=array_column(db_all('SHOW COLUMNS FROM procurement_requests'),'Field');
    if(!in_array('procurement_pr_no',$requestColumns,true))db_statement('ALTER TABLE procurement_requests ADD COLUMN procurement_pr_no VARCHAR(80) NULL AFTER pr_no');
    if(!in_array('procurement_status',$requestItemColumns,true))db_statement('ALTER TABLE procurement_request_items ADD COLUMN procurement_status VARCHAR(30) NOT NULL DEFAULT "AWAITING QUOTATION"');
    foreach(['winning_supplier'=>'VARCHAR(150)','quotation_no'=>'VARCHAR(100)','quoted_unit_cost'=>'DECIMAL(14,2)','quotation_date'=>'DATE'] as$column=>$definition){if(!in_array($column,$requestItemColumns,true))db_statement("ALTER TABLE procurement_request_items ADD COLUMN $column $definition NULL");}
}

function procurement_number(string $prefix,string $table,string $column,?int $year=null):string
{
    $year=$year?: (int)date('Y');$base=$prefix.'-'.$year.'-';
    $latest=(string)(db_value("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1",[$base.'%'])?:'');
    $next=1;if(preg_match('/(\d+)$/',$latest,$m))$next=(int)$m[1]+1;
    return $base.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function procurement_header(string $title,string $active,string $description=''):void
{
    $user=current_user();
    ?><!doctype html><html lang="en"><head><?=nursingThemeAssets()?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> - Procurement</title><link rel="stylesheet" href="../assets/procurement.css"></head><body class="module-ui proc-app"><div class="proc-shell"><?php include __DIR__.'/sidebar.php';?><main id="procWorkspace" tabindex="-1"><header class="proc-top"><div><strong><?=e($title)?></strong><small><?=e($description)?></small></div><div class="proc-user"><?=e($user['full_name']??'')?> · <?=e($user['role']??'')?></div></header><?php if($flash=flash()):?><div class="notice procurement-toast <?=e($flash['type'])?>" role="status"><?=e($flash['message'])?></div><?php endif;
}

function procurement_footer():void
{
    ?></main></div><style>.proc-confirm-dialog{width:min(430px,calc(100vw - 32px));padding:24px;border:0;border-radius:8px;background:#fff;color:#18364a;box-shadow:0 24px 70px #102b3b66;text-align:center}.proc-confirm-dialog::backdrop{background:#102b3b99;backdrop-filter:blur(2px)}.proc-confirm-icon{display:grid;place-items:center;width:44px;height:44px;margin:0 auto 12px;border-radius:50%;background:#e7f1f6;color:#0b5278;font-size:22px;font-weight:900}.proc-confirm-dialog h2{margin:0 0 8px;font-size:20px}.proc-confirm-dialog p{margin:0;color:#607583;line-height:1.55}.proc-confirm-actions{display:flex;justify-content:center;gap:10px;margin-top:22px}.proc-confirm-actions .btn{min-width:108px}</style><dialog class="proc-confirm-dialog" id="procConfirmDialog" aria-labelledby="procConfirmTitle"><div class="proc-confirm-icon" aria-hidden="true">?</div><h2 id="procConfirmTitle">Confirm Action</h2><p id="procConfirmMessage"></p><div class="proc-confirm-actions"><button class="btn light" type="button" id="procConfirmCancel">Cancel</button><button class="btn green" type="button" id="procConfirmOk">Confirm</button></div></dialog><script>(()=>{const dialog=document.getElementById('procConfirmDialog'),title=document.getElementById('procConfirmTitle'),message=document.getElementById('procConfirmMessage'),cancel=document.getElementById('procConfirmCancel'),ok=document.getElementById('procConfirmOk');let pendingForm=null,pendingSubmitter=null,confirmed=false;document.querySelectorAll('form[data-confirm],form[data-confirm-submit]').forEach(form=>form.addEventListener('submit',event=>{if(confirmed){confirmed=false;return}const submitOnly=form.dataset.confirmSubmit;if(submitOnly&&(event.submitter?.value||'')!=='submit')return;event.preventDefault();pendingForm=form;pendingSubmitter=event.submitter;const prompt=submitOnly||form.dataset.confirm||'Continue with this action?';const isApproval=/^approve\b/i.test(prompt),isSubmission=/^submit\b/i.test(prompt);title.textContent=isApproval?'Confirm Approval':(isSubmission?'Confirm Submission':'Confirm Action');message.textContent=prompt;ok.textContent=isApproval?'Approve':(isSubmission?'Submit':'Confirm');ok.className=/delete|return/i.test(prompt)?'btn red':'btn green';dialog.showModal()}));cancel.addEventListener('click',()=>{pendingForm=null;pendingSubmitter=null;dialog.close()});ok.addEventListener('click',()=>{if(!pendingForm)return;const form=pendingForm,submitter=pendingSubmitter;pendingForm=null;pendingSubmitter=null;confirmed=true;dialog.close();form.requestSubmit(submitter||undefined)});dialog.addEventListener('click',event=>{if(event.target===dialog){pendingForm=null;pendingSubmitter=null;dialog.close()}})})();</script></body></html><?php
}

ensure_procurement_schema();
