<?php

require_once __DIR__ . '/opd_helpers.php';
require_once __DIR__ . '/nis-workflow.php';

function clinicalModuleDefinitions(): array {
    return [
        'ldr' => [
            'permission' => 'ldr',
            'department' => 'LR_DR',
            'label' => 'Delivery Room / LR-DR',
            'short' => 'LR/DR',
            'icon' => 'fa-person-pregnant',
            'table' => 'delivery_room_records',
            'date_column' => 'date_time_of_delivery',
            'pages' => [
                'ldr_dashboard.php' => 'LR/DR Dashboard',
                'ldr_patients.php' => 'Delivery Room Patients',
                'ldr_labor_monitoring.php' => 'Labor Monitoring',
                'ldr_delivery_record.php' => 'Delivery Record',
                'ldr_mother_disposition.php' => 'Mother Disposition',
                'ldr_baby_disposition.php' => 'Baby Disposition',
                'ldr_transfer_baby_nicu.php' => 'Transfer Baby to NICU',
                'ldr_transfer_mother_ward.php' => 'Transfer Mother to Ward',
                'ldr_reports.php' => 'LR/DR Reports',
            ],
        ],
        'or' => [
            'permission' => 'operating_room',
            'department' => 'OR',
            'label' => 'Operating Room',
            'short' => 'OR',
            'icon' => 'fa-suitcase-medical',
            'table' => 'or_schedule',
            'date_column' => 'schedule_date_time',
            'pages' => [
                'or_dashboard.php' => 'OR Dashboard',
                'or_schedule.php' => 'OR Schedule',
                'or_for_patients.php' => 'For OR Patients',
                'or_preop_checklist.php' => 'Pre-Op Checklist',
                'or_intraop_record.php' => 'Intra-Op Record',
                'or_recovery.php' => 'Post-Op / Recovery',
                'or_transfer_ward.php' => 'Transfer Back to Ward',
                'or_transfer_micu.php' => 'Transfer to MICU',
                'or_reports.php' => 'OR Reports',
            ],
        ],
        'micu' => [
            'permission' => 'micu',
            'department' => 'ICU',
            'label' => 'MICU',
            'short' => 'MICU',
            'icon' => 'fa-heart-pulse',
            'table' => 'micu_records',
            'date_column' => 'admission_date_time',
            'pages' => [
                'micu_dashboard.php' => 'MICU Dashboard',
                'micu_patients.php' => 'MICU Patients',
                'micu_bed_tracker.php' => 'MICU Bed Tracker',
                'micu_monitoring.php' => 'MICU Monitoring',
                'micu_transfer_ward.php' => 'Trans In / Trans Out Patient',
                'micu_discharge.php' => 'MICU Discharge',
                'micu_reports.php' => 'MICU Reports',
            ],
        ],
        'nicu' => [
            'permission' => 'nicu',
            'department' => 'NICU',
            'label' => 'NICU',
            'short' => 'NICU',
            'icon' => 'fa-baby',
            'table' => 'nicu_records',
            'date_column' => 'admission_date_time',
            'pages' => [
                'nicu_dashboard.php' => 'NICU Dashboard',
                'nicu_patients.php' => 'NICU Patients',
                'nicu_inborn.php' => 'Inborn Patients',
                'nicu_outborn.php' => 'Outborn Patients',
                'nicu_bed_tracker.php' => 'NICU Bed Tracker',
                'nicu_transfer_ward.php' => 'Trans In / Trans Out Patient',
                'nicu_discharge.php' => 'NICU Discharge',
                'nicu_reports.php' => 'NICU Reports',
            ],
        ],
    ];
}

function clinicalModuleByPage(string $page): ?array {
    foreach (clinicalModuleDefinitions() as $key => $module) {
        if (isset($module['pages'][$page])) {
            $module['key'] = $key;
            $module['page_title'] = $module['pages'][$page];
            return $module;
        }
    }
    return null;
}

function clinicalModulePageMap(): array {
    $map = [];
    foreach (clinicalModuleDefinitions() as $module) {
        foreach ($module['pages'] as $page => $label) {
            $map[$page] = [$module['permission'], 'view', $module['department']];
        }
    }
    return $map;
}

function ensureClinicalModuleSchema(mysqli $conn): bool {
    ensureOpdSchema($conn);
    ensurePatientWorkflowSchema($conn);

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
            INDEX idx_movement_destination (destination_department)
        )
    ");

    $movementColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM patient_movements");
    while ($row = $result->fetch_assoc()) {
        $movementColumns[$row['Field']] = true;
    }
    $movementAdds = [
        'from_department' => "ALTER TABLE patient_movements ADD COLUMN from_department VARCHAR(60) NULL AFTER encounter_id",
        'to_department' => "ALTER TABLE patient_movements ADD COLUMN to_department VARCHAR(60) NULL AFTER from_department",
        'reason' => "ALTER TABLE patient_movements ADD COLUMN reason TEXT NULL AFTER movement_type",
        'movement_date_time' => "ALTER TABLE patient_movements ADD COLUMN movement_date_time DATETIME NULL AFTER accepted_by",
        'created_by' => "ALTER TABLE patient_movements ADD COLUMN created_by INT NULL AFTER remarks",
    ];
    foreach ($movementAdds as $column => $sql) {
        if (!isset($movementColumns[$column])) {
            $conn->query($sql);
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS or_schedule (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NOT NULL,
            source_department VARCHAR(60) NOT NULL,
            `procedure` VARCHAR(200) NULL,
            procedure_name VARCHAR(200) NOT NULL,
            surgeon VARCHAR(150) NULL,
            anesthesiologist VARCHAR(150) NULL,
            schedule_date_time DATETIME NOT NULL,
            urgency_type VARCHAR(40) NOT NULL DEFAULT 'ELECTIVE',
            status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
            pre_op_checklist_status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
            post_op_destination VARCHAR(60) NULL,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_or_patient (patient_id),
            INDEX idx_or_status (status),
            INDEX idx_or_schedule_date (schedule_date_time)
        )
    ");

    $orColumns = [];
    $orResult = $conn->query("SHOW COLUMNS FROM or_schedule");
    while ($row = $orResult->fetch_assoc()) {
        $orColumns[$row['Field']] = true;
    }
    if (!isset($orColumns['procedure'])) {
        $conn->query("ALTER TABLE or_schedule ADD COLUMN `procedure` VARCHAR(200) NULL AFTER source_department");
        $conn->query("UPDATE or_schedule SET `procedure` = procedure_name WHERE `procedure` IS NULL");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS delivery_room_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NOT NULL,
            gravida VARCHAR(20) NULL,
            para VARCHAR(20) NULL,
            age_of_gestation VARCHAR(40) NULL,
            delivery_type VARCHAR(80) NULL,
            date_time_of_delivery DATETIME NULL,
            mother_disposition VARCHAR(80) NULL,
            baby_disposition VARCHAR(80) NULL,
            baby_patient_id INT NULL,
            referred_to_nicu TINYINT(1) NOT NULL DEFAULT 0,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ldr_patient (patient_id),
            INDEX idx_ldr_baby (baby_patient_id)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS micu_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NOT NULL,
            admission_source VARCHAR(60) NULL,
            diagnosis VARCHAR(200) NULL,
            bed_no VARCHAR(40) NULL,
            admission_date_time DATETIME NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
            disposition VARCHAR(80) NULL,
            transfer_out_department VARCHAR(60) NULL,
            discharge_date_time DATETIME NULL,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_micu_patient (patient_id),
            INDEX idx_micu_status (status),
            INDEX idx_micu_bed (bed_no)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS nicu_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            encounter_id VARCHAR(30) NOT NULL,
            baby_type VARCHAR(60) NULL,
            inborn_or_outborn VARCHAR(20) NULL,
            mother_patient_id INT NULL,
            source_department VARCHAR(60) NULL,
            diagnosis VARCHAR(200) NULL,
            bed_no VARCHAR(40) NULL,
            admission_date_time DATETIME NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
            disposition VARCHAR(80) NULL,
            transfer_out_department VARCHAR(60) NULL,
            discharge_date_time DATETIME NULL,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nicu_patient (patient_id),
            INDEX idx_nicu_mother (mother_patient_id),
            INDEX idx_nicu_status (status),
            INDEX idx_nicu_bed (bed_no)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS bed_capacity_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            area_name VARCHAR(80) NOT NULL UNIQUE,
            total_bed_capacity INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $stmt = $conn->prepare("
        INSERT INTO bed_capacity_settings (area_name, total_bed_capacity, status)
        VALUES (?, ?, 'ACTIVE')
        ON DUPLICATE KEY UPDATE area_name=VALUES(area_name)
    ");
    foreach (clinicalDefaultBedCapacities() as $area => $capacity) {
        $stmt->bind_param("si", $area, $capacity);
        $stmt->execute();
    }

    return true;
}

function clinicalDefaultBedCapacities(): array {
    return [
        '2ND FLOOR WARD' => 65,
        '3RD FLOOR WARD' => 69,
        'NICU' => 5,
        'ICU / MICU' => 5,
    ];
}

function clinicalScalar(mysqli $conn, string $sql, array $params = [], string $types = ''): int {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row ? reset($row) : 0);
}

function clinicalRows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function clinicalBedCapacitySummary(mysqli $conn, string $areaName): array {
    $capacity = clinicalDefaultBedCapacities()[$areaName] ?? 0;
    $configured = clinicalScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$areaName], 's');
    if ($configured > 0) {
        $capacity = $configured;
    }

    if ($areaName === 'NICU') {
        $occupied = clinicalScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE status='ACTIVE'");
    } elseif ($areaName === 'ICU / MICU') {
        $occupied = clinicalScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE status='ACTIVE'");
    } elseif ($areaName === '2ND FLOOR WARD') {
        $occupied = clinicalScalar($conn, "SELECT COUNT(*) FROM patients WHERE status='admit_inpatient' AND ward_department IN ('OB','PEDIA','NEWBORN')");
    } else {
        $occupied = clinicalScalar($conn, "SELECT COUNT(*) FROM patients WHERE status='admit_inpatient' AND (ward_department IN ('MEDICAL','NON-TRAUMA SURGERY','TRAUMA SURGERY') OR current_bed IN (SELECT bed_id FROM beds WHERE room_number LIKE 'WARD-03%' OR room_number LIKE 'WARD-04%'))");
    }
    $vacant = $capacity - $occupied;
    $status = $vacant > 0 ? 'AVAILABLE' : ($vacant === 0 ? 'FULL' : 'OVER CAPACITY');
    return [
        'area_name' => $areaName,
        'total_bed_capacity' => $capacity,
        'occupied' => $occupied,
        'vacant' => $vacant,
        'status' => $status,
        'occupancy_percentage' => $capacity > 0 ? round(($occupied / $capacity) * 100, 2) : 0,
        'vacant_label' => 'Vacant Beds: ' . $vacant . ' / ' . $capacity . ' Beds',
    ];
}

function clinicalAllBedSummaries(mysqli $conn): array {
    return array_map(fn($area) => clinicalBedCapacitySummary($conn, $area), array_keys(clinicalDefaultBedCapacities()));
}

function clinicalMovementTypes(): array {
    return [
        'ER TO WARD', 'ER TO MICU', 'ER TO OR', 'ER TO NICU',
        'WARD TO OR', 'WARD TO MICU', 'WARD TO NICU',
        'OR TO WARD', 'OR TO MICU',
        'LR/DR TO WARD', 'LR/DR TO NICU',
        'MICU TO WARD', 'NICU TO WARD',
        'TRANSFER TO OTHER FACILITY', 'DISCHARGE', 'HAMA', 'ABSCONDED', 'EXPIRED',
        'OPD TO ER', 'OPD TO WARD', 'OPD TO OR', 'OPD TO MICU',
        'OPD TO OTHER FACILITY', 'OPD COMPLETED CONSULTATION', 'OPD FOLLOW-UP',
    ];
}

function clinicalMovementStatuses(): array {
    return ['PENDING', 'ACCEPTED', 'REJECTED', 'IN TRANSIT', 'ARRIVED', 'COMPLETED', 'CANCELLED'];
}

function clinicalCreateMovementSafe(mysqli $conn, int $patientId, string $encounterId, string $from, string $to, string $movementType, string $reason = '', string $remarks = '', string $status = 'PENDING'): bool {
    if ($status === 'PENDING' && in_array(nisDepartment($to), ['ER','OPD','WARD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'], true)) {
        try {
            nisRequestMovement($conn, $patientId, $encounterId, $from, $to, $movementType, $reason, $remarks);
            return true;
        } catch (DomainException $e) {
            return false;
        } catch (Throwable $e) {
            error_log('NIS request failed; code ' . (int)$e->getCode());
            return false;
        }
    }
    $userId = $_SESSION['user_id'] ?? null;
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        INSERT INTO patient_movements (
            patient_id, encounter_id, from_department, to_department,
            source_department, previous_department, current_department, destination_department,
            movement_type, reason, status, requested_by, movement_date_time, remarks, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $types = "issssssssssissi";
    $stmt->bind_param($types, $patientId, $encounterId, $from, $to, $from, $from, $from, $to, $movementType, $reason, $status, $userId, $now, $remarks, $userId);
    $ok = $stmt->execute();
    if ($ok) {
        $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, from_status, to_status, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $description = trim($movementType . ($remarks ? ' - ' . $remarks : ''));
        $trackStmt->bind_param("issssi", $patientId, $movementType, $description, $from, $to, $userId);
        $trackStmt->execute();
        clinicalAudit($conn, $movementType, moduleNameFromDepartment($from), $patientId, $encounterId, null, ['from' => $from, 'to' => $to, 'status' => $status, 'remarks' => $remarks]);
    }
    return $ok;
}

function moduleNameFromDepartment(string $department): string {
    return match ($department) {
        'LR_DR' => 'LR/DR',
        'OR' => 'Operating Room',
        'ICU', 'MICU' => 'MICU',
        'NICU' => 'NICU',
        default => $department,
    };
}

function clinicalAudit(mysqli $conn, string $action, string $module, ?int $patientId = null, ?string $encounterId = null, $oldValue = null, $newValue = null): void {
    try {
        ensureSecuritySchema($conn);
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $role = $_SESSION['role'] ?? null;
        $department = $_SESSION['department'] ?? null;
        $ip = clientIpAddress();
        $oldJson = $oldValue === null ? null : json_encode($oldValue);
        $newJson = $newValue === null ? null : json_encode($newValue);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $details = $action . ' in ' . $module . ' module';
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, username, role, department, ip_address, action, module, patient_id, encounter_id, old_value, new_value, user_agent, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssssisssss", $userId, $username, $role, $department, $ip, $action, $module, $patientId, $encounterId, $oldJson, $newJson, $userAgent, $details);
        $stmt->execute();
    } catch (Throwable $e) {
        logSecurityEvent($conn, $action, $module . ' audit fallback: ' . $e->getMessage(), $_SESSION['user_id'] ?? null);
    }
}

function clinicalPatientOptions(mysqli $conn, int $limit = 250): array {
    return clinicalRows($conn, "
        SELECT patient_id, patient_code, permanent_patient_id, first_name, middle_name, last_name, age, gender, status, ward_department
        FROM patients
        ORDER BY updated_at DESC, patient_id DESC
        LIMIT $limit
    ");
}

function clinicalPatientLabel(array $patient): string {
    $name = trim(($patient['last_name'] ?? '') . ', ' . ($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? ''));
    $id = $patient['permanent_patient_id'] ?: ($patient['patient_code'] ?? '');
    return $name . ' - ' . $id;
}
