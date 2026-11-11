DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS admitted_patient_documents;
DROP TABLE IF EXISTS admitted_patient_records;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS patient_tracking;
DROP TABLE IF EXISTS alarms;
DROP TABLE IF EXISTS triage;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS beds;
DROP TABLE IF EXISTS barangays;
DROP TABLE IF EXISTS municipalities;
DROP TABLE IF EXISTS provinces;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    id INT UNIQUE,
    full_name VARCHAR(150),
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE', 'STAFF_NURSE', 'DOCTOR', 'CSSR_HEAD', 'CSSR_STAFF') NOT NULL,
    department ENUM('ER', 'OPD', 'OR', 'LR_DR', 'ICU', 'NICU', 'WARD_2F', 'WARD_3F', 'CSSR') NULL,
    status ENUM('ACTIVE','INACTIVE','VOID','CANCELLED','ARCHIVED','REVERSED') NOT NULL DEFAULT 'ACTIVE',
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_password_reset_token (token_hash),
    INDEX idx_password_reset_user (user_id)
);

CREATE TABLE audit_logs (
    audit_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
);

CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role VARCHAR(50) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_approve TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_module (role, module_name),
    INDEX idx_role_permissions_role (role),
    INDEX idx_role_permissions_module (module_name)
);

INSERT INTO role_permissions (role, module_name, can_view, can_create, can_edit, can_delete, can_approve, can_print) VALUES
('SUPER_ADMIN', '*', 1, 1, 1, 1, 1, 1),
('SUPER_ADMIN', 'user_management', 1, 1, 1, 1, 1, 1),
('CHIEF_NURSE', 'nursing', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'patients', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'beds', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'reports', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'cssr_dashboard', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'cssr_inventory', 1, 0, 0, 0, 0, 1),
('CHIEF_NURSE', 'ris_requests', 1, 0, 0, 0, 0, 1),
('SUPERVISOR_NURSE', 'nursing', 1, 1, 1, 0, 0, 1),
('SUPERVISOR_NURSE', 'patients', 1, 1, 1, 0, 0, 1),
('SUPERVISOR_NURSE', 'beds', 1, 0, 0, 0, 0, 1),
('SUPERVISOR_NURSE', 'reports', 1, 0, 0, 0, 0, 1),
('SUPERVISOR_NURSE', 'ris_requests', 1, 1, 0, 0, 0, 1),
('HEAD_NURSE', 'nursing', 1, 1, 1, 0, 0, 1),
('HEAD_NURSE', 'patients', 1, 1, 1, 0, 0, 1),
('HEAD_NURSE', 'beds', 1, 0, 0, 0, 0, 1),
('HEAD_NURSE', 'reports', 1, 0, 0, 0, 0, 1),
('HEAD_NURSE', 'ris_requests', 1, 1, 0, 0, 0, 1),
('STAFF_NURSE', 'nursing', 1, 1, 1, 0, 0, 1),
('STAFF_NURSE', 'patients', 1, 1, 1, 0, 0, 1),
('STAFF_NURSE', 'beds', 1, 0, 0, 0, 0, 1),
('STAFF_NURSE', 'reports', 1, 0, 0, 0, 0, 1),
('STAFF_NURSE', 'ris_requests', 1, 1, 0, 0, 0, 1),
('DOCTOR', 'nursing', 1, 0, 0, 0, 0, 1),
('DOCTOR', 'patients', 1, 0, 1, 0, 1, 1),
('DOCTOR', 'beds', 1, 0, 0, 0, 0, 1),
('DOCTOR', 'reports', 1, 0, 0, 0, 0, 1),
('CSSR_HEAD', 'cssr_dashboard', 1, 1, 1, 0, 1, 1),
('CSSR_HEAD', 'cssr_inventory', 1, 1, 1, 0, 1, 1),
('CSSR_HEAD', 'ris_requests', 1, 1, 1, 0, 1, 1),
('CSSR_HEAD', 'reports', 1, 0, 0, 0, 0, 1),
('CSSR_HEAD', 'audit_trail', 1, 0, 0, 0, 0, 1),
('CSSR_STAFF', 'cssr_dashboard', 1, 1, 1, 0, 1, 1),
('CSSR_STAFF', 'cssr_inventory', 1, 1, 1, 0, 1, 1),
('CSSR_STAFF', 'ris_requests', 1, 1, 1, 0, 1, 1),
('CSSR_STAFF', 'reports', 1, 0, 0, 0, 0, 1);

CREATE TABLE login_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempt_lookup (username, ip_address, attempted_at)
);

CREATE TABLE provinces (
    province_id INT PRIMARY KEY AUTO_INCREMENT,
    province_name VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE municipalities (
    municipality_id INT PRIMARY KEY AUTO_INCREMENT,
    province_id INT NOT NULL,
    municipality_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (province_id) REFERENCES provinces(province_id) ON DELETE CASCADE,
    UNIQUE KEY unique_municipality (province_id, municipality_name)
);

CREATE TABLE barangays (
    barangay_id INT PRIMARY KEY AUTO_INCREMENT,
    municipality_id INT NOT NULL,
    barangay_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (municipality_id) REFERENCES municipalities(municipality_id) ON DELETE CASCADE,
    UNIQUE KEY unique_barangay (municipality_id, barangay_name)
);

CREATE TABLE beds (
    bed_id INT PRIMARY KEY AUTO_INCREMENT,
    bed_number VARCHAR(20) UNIQUE NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    bed_type ENUM('ICU', 'General', 'Emergency', 'Recovery') NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    patient_id INT NULL,
    assigned_nurse INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_nurse) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE patients (
    patient_id INT PRIMARY KEY AUTO_INCREMENT,
    permanent_patient_id VARCHAR(30) NOT NULL,
    patient_code VARCHAR(30) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    date_of_birth DATE NOT NULL,
    age INT,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    civil_status ENUM('Single','Married','Widowed','Separated','Divorced'),
    religion VARCHAR(50),
    phone VARCHAR(20),
    house_no_street VARCHAR(100),
    barangay_name VARCHAR(100),
    municipality_name VARCHAR(100),
    province_name VARCHAR(100),
    barangay_id VARCHAR(20),
    municipality_id VARCHAR(20),
    province_id VARCHAR(20),
    medical_history TEXT,
    current_bed INT NULL,
    current_doctor INT NULL,
    assigned_nurse INT NULL,
    admission_date DATETIME NOT NULL,
    triage_date DATE,
    triage_time TIME,
    arrival_time DATETIME NOT NULL,
    status ENUM('triage', 'waiting_for_consultation', 'consultation', 'treatment', 'waiting_results', 'ready_for_disposition', 'for_admission', 'discharge', 'admit_inpatient', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'er_death', 'expired') NOT NULL DEFAULT 'triage',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_bed) REFERENCES beds(bed_id) ON DELETE SET NULL,
    FOREIGN KEY (current_doctor) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_nurse) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_patient_location (province_id, municipality_id, barangay_id),
    INDEX idx_permanent_patient_id (permanent_patient_id)
);

CREATE TABLE triage (
    triage_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL UNIQUE,
    triage_level ENUM('red', 'yellow', 'green', 'black') NOT NULL,
    chief_complaint TEXT NOT NULL,
    assessment TEXT,
    medicolegal TINYINT(1) NOT NULL DEFAULT 0,
    ip TINYINT(1) NOT NULL DEFAULT 0,
    referred_by VARCHAR(150),
    coordinated TINYINT(1) NOT NULL DEFAULT 0,
    vital_signs JSON,
    vital_pr VARCHAR(20),
    vital_height VARCHAR(20),
    vital_weight VARCHAR(20),
    vital_spo2 VARCHAR(20),
    vital_cbg VARCHAR(20),
    assigned_by INT NOT NULL,
    triage_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE RESTRICT
);

CREATE TABLE patient_tracking (
    tracking_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    from_status VARCHAR(50),
    to_status VARCHAR(50),
    status_started_at DATETIME,
    status_ended_at DATETIME,
    user_id INT,
    wait_time_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE admitted_patient_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL UNIQUE,
    lab_results TEXT,
    diagnosis TEXT,
    patient_chart TEXT,
    prescription TEXT,
    notes TEXT,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE admitted_patient_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    document_type ENUM('lab', 'chart', 'prescription', 'other') NOT NULL DEFAULT 'other',
    title VARCHAR(150) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_admitted_docs_patient (patient_id)
);

CREATE TABLE alarms (
    alarm_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    alarm_type VARCHAR(50) NOT NULL,
    severity ENUM('high', 'medium', 'low') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    nurse_id INT NOT NULL,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    assigned_beds JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nurse_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE INDEX idx_patient_status ON patients(status);
CREATE INDEX idx_patient_admission ON patients(admission_date);
CREATE INDEX idx_bed_status ON beds(status);
CREATE INDEX idx_triage_level ON triage(triage_level);
CREATE INDEX idx_alarm_active ON alarms(is_active);
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_patient_code ON patients(patient_code);
