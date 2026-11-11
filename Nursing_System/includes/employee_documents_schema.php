<?php
function employeeDocumentCanVerify(string $role): bool {
    return in_array(strtoupper(trim($role)),['SECRETARY','SUPER_ADMIN','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE','SUPERVISOR'],true);
}

function edColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt=$conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->bind_param('ss',$table,$column);$stmt->execute();return (int)$stmt->get_result()->fetch_row()[0]>0;
}

function ensureEmployeeDocumentsSchema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS nursing_employee_documents (
      id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,category VARCHAR(80) NOT NULL,document_type VARCHAR(120) NOT NULL,
      file_name VARCHAR(255) NOT NULL,original_name VARCHAR(255) NOT NULL,issue_date DATE NULL,expiry_date DATE NULL,
      version_no INT NOT NULL DEFAULT 1,verification_status VARCHAR(30) NOT NULL DEFAULT 'FOR VERIFICATION',notes TEXT NULL,
      uploaded_by INT NULL,verified_by INT NULL,verified_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_employee_docs_employee(employee_id),INDEX idx_employee_docs_expiry(expiry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns=[
      'document_title'=>"VARCHAR(180) NULL AFTER document_type",'document_number'=>"VARCHAR(100) NULL AFTER document_title",
      'issuing_organization'=>"VARCHAR(180) NULL AFTER expiry_date",'document_status'=>"VARCHAR(30) NOT NULL DEFAULT 'ACTIVE' AFTER issuing_organization",
      'verification_remarks'=>"TEXT NULL AFTER verification_status",'parent_document_id'=>"INT NULL AFTER version_no",'archived_at'=>"DATETIME NULL AFTER verified_at",
      'ipcr_start_date'=>"DATE NULL AFTER expiry_date",'ipcr_end_date'=>"DATE NULL AFTER ipcr_start_date"
    ];
    foreach($columns as$name=>$definition)if(!edColumnExists($conn,'nursing_employee_documents',$name)){
        if(!$conn->query("ALTER TABLE nursing_employee_documents ADD COLUMN $name $definition"))throw new RuntimeException('Employee Documents upgrade failed: '.$conn->error);
    }
    $queries=[
      "CREATE TABLE IF NOT EXISTS nursing_document_requirements(id INT AUTO_INCREMENT PRIMARY KEY,document_name VARCHAR(160) NOT NULL,category VARCHAR(80) NOT NULL,required_for VARCHAR(100) NULL,department VARCHAR(50) NULL,position VARCHAR(80) NULL,employment_type VARCHAR(80) NULL,has_expiration TINYINT(1) NOT NULL DEFAULT 0,validity_months INT NULL,is_mandatory TINYINT(1) NOT NULL DEFAULT 1,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_doc_req_active(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS nursing_document_notifications(id INT AUTO_INCREMENT PRIMARY KEY,employee_document_id INT NULL,employee_id INT NOT NULL,notification_type VARCHAR(60) NOT NULL,message VARCHAR(255) NOT NULL,notification_date DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'UNREAD',reviewed_by INT NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_doc_notice(employee_document_id,notification_type,notification_date),INDEX idx_doc_notice_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS nursing_document_audit_logs(id INT AUTO_INCREMENT PRIMARY KEY,employee_document_id INT NULL,user_id INT NULL,employee_id INT NULL,action VARCHAR(80) NOT NULL,remarks TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_doc_audit_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS nursing_employee_leave_summary(id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,record_year SMALLINT NOT NULL,late_count INT NOT NULL DEFAULT 0,vacation_leave_used DECIMAL(7,2) NOT NULL DEFAULT 0,sick_leave_used DECIMAL(7,2) NOT NULL DEFAULT 0,special_privilege_leave_used DECIMAL(7,2) NOT NULL DEFAULT 0,updated_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_employee_leave_year(employee_id,record_year),INDEX idx_leave_year(record_year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS nursing_employee_attendance(id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,record_date DATE NOT NULL,duty_schedule VARCHAR(30) NOT NULL,scheduled_start TIME NOT NULL,scheduled_end TIME NOT NULL,actual_time_in TIME NULL,late_minutes INT NOT NULL DEFAULT 0,leave_type VARCHAR(40) NULL,leave_days DECIMAL(4,2) NOT NULL DEFAULT 0,remarks VARCHAR(255) NULL,encoded_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_employee_attendance_date(employee_id,record_date),INDEX idx_attendance_date(record_date),INDEX idx_attendance_employee(employee_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach($queries as$sql)if(!$conn->query($sql))throw new RuntimeException('Employee Documents setup failed: '.$conn->error);
    foreach(['wellness_leave_used','calamity_leave_used']as$leaveColumn)if(!edColumnExists($conn,'nursing_employee_leave_summary',$leaveColumn))$conn->query("ALTER TABLE nursing_employee_leave_summary ADD COLUMN $leaveColumn DECIMAL(7,2) NOT NULL DEFAULT 0 AFTER special_privilege_leave_used");
    if(!edColumnExists($conn,'nursing_employee_leave_summary','late_minutes_total'))$conn->query("ALTER TABLE nursing_employee_leave_summary ADD COLUMN late_minutes_total INT NOT NULL DEFAULT 0 AFTER late_count");
    if(!edColumnExists($conn,'nursing_employee_leave_summary','absent_count'))$conn->query("ALTER TABLE nursing_employee_leave_summary ADD COLUMN absent_count INT NOT NULL DEFAULT 0 AFTER late_minutes_total");
    if(!edColumnExists($conn,'nursing_employee_attendance','leave_end_date'))$conn->query("ALTER TABLE nursing_employee_attendance ADD COLUMN leave_end_date DATE NULL AFTER record_date");
    // Keep this as two simple statements. MariaDB 10.4 can crash while optimizing
    // INSERT ... SELECT ... WHERE NOT EXISTS after an unclean shutdown.
    $defaultRequirements=[
      ['Proof of Appointment','Employment Documents',0],['PDS','Employment Documents',0],['IPCR','Employment Documents',0],
      ['Diploma','Professional Documents',0],['PRC License','Professional Documents',1],['Civil Service Eligibility','Professional Documents',0],['PNA','Professional Documents',1],
      ['BLS','Training & Certificates',1],['ACLS','Training & Certificates',1],['ER Training','Training & Certificates',0],
      ['OR Training','Training & Certificates',0],['DR Training','Training & Certificates',0],['NICU Training','Training & Certificates',0],
      ['ICU Training','Training & Certificates',0],['Other Trainings','Training & Certificates',0]
    ];
    $requirementCheck=$conn->prepare('SELECT id FROM nursing_document_requirements WHERE document_name=? AND category=? LIMIT 1');
    $requirementInsert=$conn->prepare('INSERT INTO nursing_document_requirements(document_name,category,required_for,has_expiration,is_mandatory,is_active) VALUES(?,?,?,?,?,?)');
    $requiredFor='All Nursing Employees';$isMandatory=1;$isActive=1;
    foreach($defaultRequirements as[$requirementName,$requirementCategory,$hasExpiration]){
        $requirementCheck->bind_param('ss',$requirementName,$requirementCategory);$requirementCheck->execute();
        if(!$requirementCheck->get_result()->fetch_row()){
            $requirementInsert->bind_param('sssiii',$requirementName,$requirementCategory,$requiredFor,$hasExpiration,$isMandatory,$isActive);$requirementInsert->execute();
        }
    }
    $requirementCheck->close();$requirementInsert->close();
}

function employeeDocumentAudit(mysqli $conn,?int $documentId,int $userId,int $employeeId,string $action,string $remarks=''):void
{
    $stmt=$conn->prepare('INSERT INTO nursing_document_audit_logs(employee_document_id,user_id,employee_id,action,remarks) VALUES(?,?,?,?,?)');
    $stmt->bind_param('iiiss',$documentId,$userId,$employeeId,$action,$remarks);$stmt->execute();
}
