<?php

function opdDispositionOptions(): array {
    return [
        'COMPLETED CONSULTATION' => 'COMPLETED CONSULTATION',
        'REFERRED TO ER' => 'REFERRED TO ER',
        'FOR ADMISSION TO WARD' => 'FOR ADMISSION TO WARD',
        'FOR OR SCHEDULE' => 'FOR OR SCHEDULE',
        'REFERRED TO OTHER FACILITY' => 'REFERRED TO OTHER FACILITY',
        'FOR FOLLOW-UP' => 'FOR FOLLOW-UP',
        'CANCELLED' => 'CANCELLED',
    ];
}

function opdClinicTypeOptions(): array {
    return [
        'GENERAL OPD' => 'GENERAL OPD',
        'MEDICAL' => 'MEDICAL',
        'SURGERY' => 'SURGERY',
        'PEDIA' => 'PEDIA',
        'OB-GYNE' => 'OB-GYNE',
        'FOLLOW-UP' => 'FOLLOW-UP',
    ];
}

function opdMovementTypeForDisposition(string $disposition): string {
    $map = [
        'REFERRED TO ER' => 'OPD TO ER',
        'FOR ADMISSION TO WARD' => 'OPD TO WARD',
        'FOR OR SCHEDULE' => 'OPD TO OR',
        'REFERRED TO OTHER FACILITY' => 'OPD TO OTHER FACILITY',
        'COMPLETED CONSULTATION' => 'OPD COMPLETED CONSULTATION',
        'FOR FOLLOW-UP' => 'OPD FOLLOW-UP',
    ];
    return $map[$disposition] ?? 'OPD COMPLETED CONSULTATION';
}

function opdDestinationForDisposition(string $disposition, string $referredToDepartment = ''): string {
    $map = [
        'REFERRED TO ER' => 'ER',
        'FOR ADMISSION TO WARD' => 'WARD',
        'FOR OR SCHEDULE' => 'OR',
        'REFERRED TO OTHER FACILITY' => 'OTHER FACILITY',
        'COMPLETED CONSULTATION' => 'OPD',
        'FOR FOLLOW-UP' => 'OPD',
        'CANCELLED' => 'OPD',
    ];
    return $referredToDepartment !== '' ? $referredToDepartment : ($map[$disposition] ?? 'OPD');
}

function ensureOpdSchema(mysqli $conn): bool {
    ensureSecuritySchema($conn);
    ensureUserRoleSchema($conn);
    ensurePatientWorkflowSchema($conn);

    $conn->query("
        CREATE TABLE IF NOT EXISTS opd_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NOT NULL,
            opd_number VARCHAR(30) NOT NULL UNIQUE,
            consultation_date_time DATETIME NOT NULL,
            clinic_type VARCHAR(80) NOT NULL,
            chief_complaint TEXT NOT NULL,
            diagnosis VARCHAR(200) NULL,
            attending_physician VARCHAR(150) NULL,
            disposition VARCHAR(60) NOT NULL,
            referred_to_department VARCHAR(60) NULL,
            follow_up_date DATE NULL,
            remarks TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_opd_patient (patient_id),
            INDEX idx_opd_encounter (encounter_id),
            INDEX idx_opd_disposition (disposition),
            INDEX idx_opd_status (status),
            CONSTRAINT fk_opd_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
            CONSTRAINT fk_opd_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS patient_movements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NULL,
            source_department VARCHAR(60) NOT NULL,
            previous_department VARCHAR(60) NULL,
            current_department VARCHAR(60) NULL,
            destination_department VARCHAR(60) NOT NULL,
            movement_type VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
            requested_by INT NULL,
            accepted_by INT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_movement_patient (patient_id),
            INDEX idx_movement_type (movement_type),
            INDEX idx_movement_status (status),
            INDEX idx_movement_destination (destination_department),
            CONSTRAINT fk_movement_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
            CONSTRAINT fk_movement_requested_by FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE SET NULL,
            CONSTRAINT fk_movement_accepted_by FOREIGN KEY (accepted_by) REFERENCES users(user_id) ON DELETE SET NULL
        )
    ");

    return true;
}

function opdAudit(mysqli $conn, string $action, ?int $patientId = null, ?string $encounterId = null, $oldValue = null, $newValue = null): void {
    try {
        ensureSecuritySchema($conn);
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $role = $_SESSION['role'] ?? null;
        $department = $_SESSION['department'] ?? null;
        $ip = clientIpAddress();
        $module = 'OPD';
        $oldJson = $oldValue === null ? null : json_encode($oldValue);
        $newJson = $newValue === null ? null : json_encode($newValue);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $details = $action . ' in OPD module';
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, username, role, department, ip_address, action, module, patient_id, encounter_id, old_value, new_value, user_agent, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssssisssss", $userId, $username, $role, $department, $ip, $action, $module, $patientId, $encounterId, $oldJson, $newJson, $userAgent, $details);
        $stmt->execute();
    } catch (Throwable $e) {
        logSecurityEvent($conn, $action, 'OPD audit fallback: ' . $e->getMessage(), $_SESSION['user_id'] ?? null);
    }
}

function generateOpdNumber(mysqli $conn, ?string $date = null): string {
    $date = $date ?: date('Y-m-d');
    $prefix = 'OPD-' . date('Ymd', strtotime($date)) . '-';
    $stmt = $conn->prepare("SELECT opd_number FROM opd_records WHERE opd_number LIKE CONCAT(?, '%') ORDER BY opd_number DESC LIMIT 1");
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $next = 1;
    if ($row && preg_match('/-(\d+)$/', $row['opd_number'], $matches)) {
        $next = (int) $matches[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function createOpdMovement(mysqli $conn, int $patientId, string $encounterId, string $disposition, string $destination, string $remarks = ''): void {
    $movementType = opdMovementTypeForDisposition($disposition);
    if ($destination === 'MICU') {
        $movementType = 'OPD TO MICU';
    }
    $status = in_array($disposition, ['COMPLETED CONSULTATION', 'FOR FOLLOW-UP'], true) ? 'COMPLETED' : 'PENDING';
    if ($disposition === 'CANCELLED') {
        $status = 'CANCELLED';
    }
    $source = 'OPD';
    $current = 'OPD';
    $requestedBy = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("
        INSERT INTO patient_movements (
            patient_id, encounter_id, source_department, previous_department, current_department,
            destination_department, movement_type, status, requested_by, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssssssis", $patientId, $encounterId, $source, $source, $current, $destination, $movementType, $status, $requestedBy, $remarks);
    $stmt->execute();

    $trackAction = $movementType;
    $trackDescription = $movementType . ' - ' . $remarks;
    $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, from_status, to_status, user_id) VALUES (?, ?, ?, 'OPD', ?, ?)");
    $trackStmt->bind_param("isssi", $patientId, $trackAction, $trackDescription, $destination, $requestedBy);
    $trackStmt->execute();
}

function opdRecordRows(mysqli $conn, string $where = '1=1', array $params = [], string $types = ''): array {
    $sql = "
        SELECT
            o.*,
            p.permanent_patient_id,
            p.patient_code,
            p.first_name,
            p.last_name,
            p.middle_name,
            p.age,
            p.gender,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name
        FROM opd_records o
        INNER JOIN patients p ON p.patient_id = o.patient_id
        WHERE $where
        ORDER BY o.consultation_date_time DESC, o.id DESC
    ";
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function opdScalar(mysqli $conn, string $sql, array $params = [], string $types = ''): int {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) reset($row) : 0;
}
