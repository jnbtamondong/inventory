<?php

// Aggregate reads only. No schema setup, patient changes, or bed assignments.
function nursingOverviewCount(mysqli $conn, string $sql, array $params = [], string $types = ''): ?int {
    try {
        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row && $row['total'] !== null ? (int) $row['total'] : null;
    } catch (mysqli_sql_exception $e) {
        error_log('Nursing overview aggregate unavailable: ' . $e->getMessage());
        return null;
    }
}

function nursingOverviewData(mysqli $conn, string $startDate, string $endDate): array {
    $rooms = array_values(array_unique(obPediaWardRoomNumbers()));
    $roomSql = implode(',', array_map(fn($room) => "'" . $conn->real_escape_string($room) . "'", $rooms));
    $bedConfigs = [
        'ER' => ["room_number LIKE 'ER-%'", null],
        'Medical/Surgery Ward' => ["room_number LIKE 'WARD-03%' OR room_number LIKE 'WARD-04%'", '3RD FLOOR WARD'],
        'OB/Pedia Ward' => ["room_number IN ($roomSql)", '2ND FLOOR WARD'],
        'NICU' => [null, 'NICU'],
        'ICU' => [null, 'ICU / MICU'],
    ];
    $beds = [];
    foreach ($bedConfigs as $label => [$where, $capacityArea]) {
        $capacity = $capacityArea ? nursingOverviewCount($conn, "SELECT total_bed_capacity AS total FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$capacityArea], 's') : null;
        if ($where !== null) {
            $capacity = $capacity > 0 ? $capacity : nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM beds WHERE $where");
            $occupied = nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM beds WHERE ($where) AND status='occupied'");
            $maintenance = nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM beds WHERE ($where) AND status='maintenance'");
        } else {
            $table = $label === 'NICU' ? 'nicu_records' : 'micu_records';
            $occupied = nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM $table WHERE status='ACTIVE'");
            $maintenance = 0;
            // Match the configured default used by the existing clinical dashboard.
            $capacity = $capacity > 0 ? $capacity : 5;
        }
        $available = $capacity !== null && $occupied !== null && $maintenance !== null
            ? max(0, $capacity - $occupied - $maintenance) : null;
        $beds[$label] = ['capacity' => $capacity, 'occupied' => $occupied, 'available' => $available, 'maintenance' => $maintenance];
    }

    $activeList = "'" . implode("','", array_map([$conn, 'real_escape_string'], activePatientStatuses())) . "'";
    $census = [
        ['name' => 'ER', 'icon' => 'activity', 'basis' => 'Current patients', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM patients WHERE status IN ($activeList)")],
        ['name' => 'Medical/Surgery Ward', 'icon' => 'hospital', 'basis' => 'Current admitted patients', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM patients WHERE status='admit_inpatient' AND ward_department IN ('MEDICAL','SURGERY','NON-TRAUMA SURGERY','TRAUMA SURGERY')")],
        ['name' => 'OB/Pedia Ward', 'icon' => 'heart', 'basis' => 'Current admitted patients', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM patients WHERE status='admit_inpatient' AND ward_department IN ('OB','PEDIA','NEWBORN')")],
        ['name' => 'OPD', 'icon' => 'person-vcard', 'basis' => 'Active consultations in coverage', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM opd_records WHERE status='ACTIVE' AND DATE(consultation_date_time) BETWEEN ? AND ?", [$startDate, $endDate], 'ss')],
        ['name' => 'NICU', 'icon' => 'stars', 'basis' => 'Current patients', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM nicu_records WHERE status='ACTIVE'")],
        ['name' => 'ICU', 'icon' => 'heart-pulse', 'basis' => 'Current ICU / MICU patients', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM micu_records WHERE status='ACTIVE'")],
        ['name' => 'OR', 'icon' => 'bandaid', 'basis' => 'Pending, accepted, in OR or recovery', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM or_schedule WHERE status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')")],
        ['name' => 'DR', 'icon' => 'heart', 'basis' => 'Delivery records in coverage', 'count' => nursingOverviewCount($conn, "SELECT COUNT(*) AS total FROM delivery_room_records WHERE DATE(COALESCE(date_time_of_delivery,created_at)) BETWEEN ? AND ?", [$startDate, $endDate], 'ss')],
        ['name' => 'ASC', 'icon' => 'clipboard2-pulse', 'basis' => 'Reported census at coverage end', 'count' => nursingOverviewCount($conn, "SELECT census AS total FROM patient_census_summary WHERE department='ASC' AND report_date=? ORDER BY created_at DESC,id DESC LIMIT 1", [$endDate], 's')],
    ];
    $totals = ['capacity' => 0, 'occupied' => 0, 'available' => 0, 'maintenance' => 0];
    foreach ($totals as $key => $value) {
        foreach ($beds as $bed) {
            if ($bed[$key] === null) {
                $totals[$key] = null;
                break;
            }
            $totals[$key] += $bed[$key];
        }
    }
    return ['beds' => $beds, 'census' => $census, 'totals' => $totals];
}
