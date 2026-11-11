DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS admitted_patient_documents;
DROP TABLE IF EXISTS admitted_patient_records;
DROP TABLE IF EXISTS password_resets;
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
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'doctor', 'nurse', 'chief_nurse', 'nurse_supervisor', 'head_nurse', 'staff_nurse', 'nursing_attendant', 'admission_staff') NOT NULL,
    department ENUM('emergency_room', 'out_patient_department') NULL,
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
    vital_signs JSON,
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
