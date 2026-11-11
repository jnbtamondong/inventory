<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/patient_helpers.php';
require_once __DIR__ . '/nis-tracker.php';

function nursingReportEnsureSchema(mysqli $conn): void {
    $tables = [
        'nursing_reports' => "CREATE TABLE IF NOT EXISTS nursing_reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            report_type VARCHAR(100) NOT NULL,
            coverage_start DATETIME NULL,
            coverage_end DATETIME NULL,
            prepared_by VARCHAR(150) NULL,
            reviewed_by VARCHAR(150) NULL,
            approved_by VARCHAR(150) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_nursing_reports_type (report_type)
        ) ENGINE=InnoDB",
        'hospital_census' => "CREATE TABLE IF NOT EXISTS hospital_census (
            id INT PRIMARY KEY AUTO_INCREMENT,
            department VARCHAR(80) NOT NULL,
            previous_midnight_census INT NOT NULL DEFAULT 0,
            admission INT NOT NULL DEFAULT 0,
            transfer_in INT NOT NULL DEFAULT 0,
            discharged INT NOT NULL DEFAULT 0,
            transfer_out INT NOT NULL DEFAULT 0,
            thoc INT NOT NULL DEFAULT 0,
            death INT NOT NULL DEFAULT 0,
            hama INT NOT NULL DEFAULT 0,
            census_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hospital_census_date (census_date)
        ) ENGINE=InnoDB",
        'er_statistics' => "CREATE TABLE IF NOT EXISTS er_statistics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            department VARCHAR(80) NOT NULL,
            disposition VARCHAR(80) NULL,
            triage_category VARCHAR(80) NULL,
            turnaround_minutes INT NOT NULL DEFAULT 0,
            report_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_er_statistics_date (report_date)
        ) ENGINE=InnoDB",
        'er_reportable_cases' => "CREATE TABLE IF NOT EXISTS er_reportable_cases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            category VARCHAR(30) NOT NULL,
            case_name VARCHAR(180) NOT NULL,
            number_of_cases INT NOT NULL DEFAULT 0,
            date_reported DATE NULL,
            department VARCHAR(80) NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reportable_category (category, case_name)
        ) ENGINE=InnoDB",
        'patient_census_summary' => "CREATE TABLE IF NOT EXISTS patient_census_summary (
            id INT PRIMARY KEY AUTO_INCREMENT,
            department VARCHAR(80) NOT NULL,
            bed_capacity INT NOT NULL DEFAULT 0,
            census INT NOT NULL DEFAULT 0,
            admission INT NOT NULL DEFAULT 0,
            discharged INT NOT NULL DEFAULT 0,
            transfer_in INT NOT NULL DEFAULT 0,
            transfer_out INT NOT NULL DEFAULT 0,
            absconded INT NOT NULL DEFAULT 0,
            hama INT NOT NULL DEFAULT 0,
            death INT NOT NULL DEFAULT 0,
            report_date DATE NOT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        'nursing_kpi' => "CREATE TABLE IF NOT EXISTS nursing_kpi (
            id INT PRIMARY KEY AUTO_INCREMENT,
            total_er_consultation INT NOT NULL DEFAULT 0,
            disposition_lt_4h INT NOT NULL DEFAULT 0,
            disposition_gt_4h INT NOT NULL DEFAULT 0,
            target_percentage DECIMAL(6,2) NOT NULL DEFAULT 95.00,
            pending_admission INT NOT NULL DEFAULT 0,
            with_room INT NOT NULL DEFAULT 0,
            report_date DATE NOT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        'ward_discharge_tat' => "CREATE TABLE IF NOT EXISTS ward_discharge_tat (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ward VARCHAR(80) NOT NULL,
            discharge_lt_6h INT NOT NULL DEFAULT 0,
            discharge_gt_6h INT NOT NULL DEFAULT 0,
            target_percentage DECIMAL(6,2) NOT NULL DEFAULT 100.00,
            pending_discharge INT NOT NULL DEFAULT 0,
            pending_billing INT NOT NULL DEFAULT 0,
            report_date DATE NOT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        'ward_discharge_tat_records' => "CREATE TABLE IF NOT EXISTS ward_discharge_tat_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            ward VARCHAR(80) NULL,
            date_mgh_ordered DATE NOT NULL,
            time_mgh_order TIME NOT NULL,
            chart_to_phic_signature_at DATETIME NOT NULL,
            signed_soa_received_at DATETIME NOT NULL,
            disposition_at DATETIME NOT NULL,
            tagubilin_receiver_signature VARCHAR(160) NOT NULL,
            patient_survey_signature VARCHAR(160) NOT NULL,
            actual_discharge_tat_minutes INT NOT NULL DEFAULT 0,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_ward_tat_patient (patient_id),
            INDEX idx_ward_tat_ward (ward),
            INDEX idx_ward_tat_disposition (disposition_at)
        ) ENGINE=InnoDB",
        'or_safe_operation' => "CREATE TABLE IF NOT EXISTS or_safe_operation (
            id INT PRIMARY KEY AUTO_INCREMENT,
            schedule_type VARCHAR(40) NOT NULL,
            service VARCHAR(80) NOT NULL,
            total_cases INT NOT NULL DEFAULT 0,
            done_cases INT NOT NULL DEFAULT 0,
            report_date DATE NOT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
    ];
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
}

function nursingReportCanAccess(string $key): bool {
    if (!hasPermission('reports','view')) return false;
    $allDepartments=true;
    foreach(['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'] as $unit) {
        if(!isDepartmentAllowed($unit)){$allDepartments=false;break;}
    }
    if ($allDepartments) {
        return true;
    }
    $department = canonicalDepartment($_SESSION['department'] ?? '');
    $allowed = [
        'ER' => ['er_statistics', 'kpi'],
        'WARD_2F' => ['bed_tracker', 'patient_census', 'ward_tat', 'monthly'],
        'WARD_3F' => ['bed_tracker', 'patient_census', 'ward_tat', 'monthly'],
        'ICU' => ['bed_tracker', 'patient_census', 'ward_tat', 'monthly'],
        'NICU' => ['bed_tracker', 'patient_census', 'ward_tat', 'monthly'],
        'LR_DR' => ['hospital_census', 'monthly'],
        'OR' => ['or_safe', 'monthly'],
    ];
    return in_array($key, $allowed[$department] ?? [], true);
}

function nrScalar(mysqli $conn, string $sql, array $params = [], string $types = ''): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return 0; }
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row ? reset($row) : 0);
}

function nrRows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return []; }
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function nrPct(int $part, int $total): string {
    return $total > 0 ? number_format(($part / $total) * 100, 2) . '%' : '0%';
}

function nrDateRange(): array {
    $start = $_GET['coverage_start'] ?? date('Y-m-d\T00:00');
    $end = $_GET['coverage_end'] ?? date('Y-m-d\T23:59');
    $startDt = DateTime::createFromFormat('Y-m-d\TH:i', $start) ?: new DateTime(date('Y-m-d 00:00'));
    $endDt = DateTime::createFromFormat('Y-m-d\TH:i', $end) ?: new DateTime(date('Y-m-d 23:59'));
    if ($startDt > $endDt) { [$startDt, $endDt] = [$endDt, $startDt]; }
    return [$startDt->format('Y-m-d H:i:s'), $endDt->format('Y-m-d H:i:s'), $startDt->format('Y-m-d\TH:i'), $endDt->format('Y-m-d\TH:i')];
}

function nrPatientNameSql(): string {
    return "CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), ''))";
}

function nursingReportConfig(string $key, mysqli $conn, string $start, string $end): array {
    $departments = ['PEDIA', 'OB', 'MEDICAL', 'SURGERY', 'NEWBORN', 'MICU', 'NICU', 'OR COMPLEX'];
    $visibleUnits=nisTrackerDepartments('reports');
    if(count($visibleUnits)<count(nisTrackerUnits())){
        $specialties=['WARD_2F'=>['PEDIA','OB','NEWBORN'],'WARD_3F'=>['MEDICAL','SURGERY'],'ICU'=>['MICU'],'NICU'=>['NICU'],'OR'=>['OR COMPLEX']];
        $visibleSpecialties=[];foreach($visibleUnits as $unit)array_push($visibleSpecialties,...($specialties[$unit]??[]));
        $departments=array_values(array_intersect($departments,$visibleSpecialties));
    }
    $dischargeStatuses = "('discharge','discharged','trans_out','referred','ama','hama','thoc','absconded','dama','doa','death','er_death','expired')";
    $activeStatuses = "('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission','admit_inpatient')";
    $rangeParams = [$start, $end];

    if ($key === 'bed_tracker') {
        $beds = nrRows($conn, "SELECT area_name, total_bed_capacity, status FROM bed_capacity_settings WHERE status='ACTIVE' ORDER BY area_name");
        [$clinicalScope,$clinicalParams]=nisTrackerClinicalScope();
        $patients = nrRows($conn, "SELECT COALESCE(NULLIF(p.ward_department,''),'ER PENDING') area_name, p.current_bed room_number, " . nrPatientNameSql() . " patient_name, p.admission_date, p.status, p.final_diagnosis remarks FROM patients p WHERE (p.status IN $activeStatuses OR p.status='admit_inpatient') AND $clinicalScope ORDER BY area_name, p.admission_date DESC LIMIT 300",$clinicalParams,str_repeat('s',count($clinicalParams)));
        $rows = [];
        $capacity = 0;
        $occupied = 0;
        $overcapacity = 0;
        foreach ($beds as $bed) {
            $occupiedCount = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE status='admit_inpatient' AND (ward_department LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', ward_department, '%'))", [$bed['area_name'], $bed['area_name']], 'ss');
            $vacantCount = (int) $bed['total_bed_capacity'] - $occupiedCount;
            $capacity += (int) $bed['total_bed_capacity'];
            $occupied += $occupiedCount;
            if ($vacantCount < 0) { $overcapacity++; }
            $rows[] = [
                'Area / Department' => $bed['area_name'],
                'Bed Capacity' => (int) $bed['total_bed_capacity'],
                'Occupied' => $occupiedCount,
                'Vacant' => $vacantCount,
                'Room Number' => '',
                'Patient Name' => '',
                'Date Admitted' => '',
                'Status' => ($vacantCount < 0 ? 'Overcapacity' : $bed['status']),
                'Remarks' => '',
            ];
        }
        foreach ($patients as $patient) {
            $rows[] = [
                'Area / Department' => $patient['area_name'],
                'Bed Capacity' => '',
                'Occupied' => '',
                'Vacant' => '',
                'Room Number' => $patient['room_number'],
                'Patient Name' => $patient['patient_name'],
                'Date Admitted' => $patient['admission_date'],
                'Status' => $patient['status'],
                'Remarks' => $patient['remarks'],
            ];
        }
        return ['title' => 'Bed Tracker Report', 'summary' => [
            'Total Bed Capacity' => $capacity,
            'Total Occupied' => $occupied,
            'Total Vacant' => $capacity - $occupied,
            'Total Inpatients' => count($patients),
            'Overcapacity Areas' => $overcapacity,
        ], 'columns' => ['Area / Department','Bed Capacity','Occupied','Vacant','Room Number','Patient Name','Date Admitted','Status','Remarks'], 'rows' => $rows];
    }

    if ($key === 'hospital_census') {
        $rows = [];
        foreach (['OB','PEDIA','MEDICAL','SURGERY','MICU','NICU','ER PENDING'] as $dept) {
            $admission = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER PENDING')=? AND admission_date BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $transferIn = nrScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE destination_department=? AND created_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $transferOut = nrScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE source_department=? AND created_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $discharged = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER PENDING')=? AND status IN $dischargeStatuses AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $death = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER PENDING')=? AND status IN ('death','er_death','expired','doa') AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $hama = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER PENDING')=? AND status IN ('hama','ama','dama') AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $previous = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER PENDING')=? AND admission_date < ? AND (updated_at >= ? OR status IN $activeStatuses)", [$dept, $start, $start], 'sss');
            $total = $previous + $admission + $transferIn;
            $deductions = $discharged + $transferOut + $death + $hama;
            $rows[] = ['Department'=>$dept,'Previous Midnight Census'=>$previous,'Admission'=>$admission,'Transfer-In'=>$transferIn,'Total'=>$total,'Discharged'=>$discharged,'Transfer-Out'=>$transferOut,'THOC'=>0,'Death'=>$death,'HAMA'=>$hama,'Total Deductions'=>$deductions,'Admit-Discharge < 24 Hours'=>0,'Current Census as of 12MN'=>$total - $deductions];
        }
        return ['title' => 'Hospital Statistics / 24-Hour Census Report', 'summary' => [], 'columns' => array_keys($rows[0]), 'rows' => $rows];
    }

    if ($key === 'er_statistics') {
        $rows = [];
        foreach (['MEDICAL','PEDIA','NEWBORN','OB','SURGERY','TRAUMA','NON-TRAUMA'] as $dept) {
            $consult = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'MEDICAL')=? AND arrival_time BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $admission = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'MEDICAL')=? AND status IN ('for_admission','admit_inpatient','admission') AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $discharged = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'MEDICAL')=? AND status IN ('discharge','discharged') AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $pending = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'MEDICAL')=? AND status IN ('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission')", [$dept], 's');
            $lt4 = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'MEDICAL')=? AND TIMESTAMPDIFF(HOUR, arrival_time, updated_at) < 4 AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $gt4 = max(0, $consult - $lt4);
            $rows[] = ['Department'=>$dept,'ER Consult'=>$consult,'Admission'=>$admission,'Transfer-In'=>0,'Discharged'=>$discharged,'Transfer to Other Facility'=>0,'ER Death'=>0,'DOA'=>0,'Refused Admission'=>0,'HAMA'=>0,'Absconded'=>0,'On Observation'=>0,'Turnaround Time < 4 Hours'=>$lt4,'Turnaround Time > 4 Hours'=>$gt4,'Pending'=>$pending];
        }
        return ['title' => 'Emergency Room Statistics Report', 'summary' => [], 'columns' => array_keys($rows[0]), 'rows' => $rows, 'extra' => nursingReportErExtra($conn, $start, $end)];
    }

    if ($key === 'patient_census') {
        $rows = [];
        foreach ($departments as $dept) {
            $capacity = nrScalar($conn, "SELECT COALESCE(SUM(total_bed_capacity),0) FROM bed_capacity_settings WHERE area_name LIKE CONCAT('%', ?, '%')", [$dept], 's');
            $census = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'')=? AND status IN $activeStatuses", [$dept], 's');
            $admission = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'')=? AND admission_date BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $discharged = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'')=? AND status IN ('discharge','discharged') AND updated_at BETWEEN ? AND ?", [$dept, $start, $end], 'sss');
            $rows[] = ['Department'=>$dept,'Bed Capacity'=>$capacity,'Census'=>$census,'Admission'=>$admission,'Discharged'=>$discharged,'Transfer-In'=>0,'Transfer-Out'=>0,'Absconded'=>0,'HAMA'=>0,'Death'=>0,'Remarks'=>''];
        }
        return ['title' => 'Patient Census Summary', 'summary' => [], 'columns' => array_keys($rows[0]), 'rows' => $rows];
    }

    if ($key === 'kpi') {
        $total = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE arrival_time BETWEEN ? AND ?", $rangeParams, 'ss');
        $lt4 = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE TIMESTAMPDIFF(HOUR, arrival_time, updated_at) < 4 AND updated_at BETWEEN ? AND ?", $rangeParams, 'ss');
        $gt4 = max(0, $total - $lt4);
        $pending = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE status='for_admission'");
        $rows = [['Total ER Consultation'=>$total,'With Disposition < 4 Hours'=>$lt4,'With Disposition > 4 Hours'=>$gt4,'Target Percentage'=>'95%','Accomplishment Percentage'=>nrPct($lt4, $total),'Pending Admission'=>$pending,'With Room'=>0,'Remarks'=>'']];
        return ['title' => 'Key Performance Indicators', 'summary' => [], 'columns' => array_keys($rows[0]), 'rows' => $rows];
    }

    if ($key === 'ward_tat') {
        $wardNames=['WARD_2F'=>['WARD_2F','2nd Floor Ward','2ND FLOOR WARD','OB/Pedia Ward','OB / Pedia Ward'],
            'WARD_3F'=>['WARD_3F','3rd Floor Ward','3RD FLOOR WARD','Medical/Surgery Ward','Medical / Surgery Ward'],
            'ICU'=>['ICU','MICU','ICU / MICU'],'NICU'=>['NICU']];
        $clinicalUnits=nisTrackerDepartments();$labels=[];
        foreach($clinicalUnits as $unit)array_push($labels,...($wardNames[$unit]??[]));
        $tatScope=count($clinicalUnits)===count(nisTrackerUnits())?'1=1':($labels?'w.ward IN ('.implode(',',array_fill(0,count($labels),'?')).')':'0=1');
        $tatParams=array_merge($rangeParams,count($clinicalUnits)===count(nisTrackerUnits())?[]:$labels);
        $detailRows = nrRows($conn, "
            SELECT
                w.*,
                " . nrPatientNameSql() . " AS patient_name,
                p.permanent_patient_id,
                p.patient_code,
                p.final_diagnosis
            FROM ward_discharge_tat_records w
            INNER JOIN patients p ON p.patient_id = w.patient_id
            WHERE w.disposition_at BETWEEN ? AND ? AND $tatScope
            ORDER BY w.disposition_at DESC
        ", $tatParams, str_repeat('s',count($tatParams)));

        $rows = [];
        foreach ($detailRows as $row) {
            $rows[] = [
                'Ward' => $row['ward'] ?: 'WARD',
                'Patient ID' => $row['permanent_patient_id'] ?: $row['patient_code'],
                'Patient Name' => $row['patient_name'],
                'Date of MGH Ordered' => $row['date_mgh_ordered'],
                'Time of MGH Order' => $row['time_mgh_order'],
                'Time Chart to PhilHealth / PHIC Signature' => $row['chart_to_phic_signature_at'],
                'Time Signed SOA Received from Billing / Billing Signature' => $row['signed_soa_received_at'],
                'Time of Disposition' => $row['disposition_at'],
                "Tagubilin Receiving Patient's Signature" => $row['tagubilin_receiver_signature'],
                "Patient's Survey Form / Patient's Signature" => $row['patient_survey_signature'],
                'Actual Patient Discharge TAT Duration' => formatDaysHoursMinutes((int) $row['actual_discharge_tat_minutes']),
                'Remarks' => $row['remarks'] ?: ($row['final_diagnosis'] ?? ''),
            ];
        }

        if (empty($rows)) {
            $rows[] = [
                'Ward' => '',
                'Patient ID' => '',
                'Patient Name' => '',
                'Date of MGH Ordered' => '',
                'Time of MGH Order' => '',
                'Time Chart to PhilHealth / PHIC Signature' => '',
                'Time Signed SOA Received from Billing / Billing Signature' => '',
                'Time of Disposition' => '',
                "Tagubilin Receiving Patient's Signature" => '',
                "Patient's Survey Form / Patient's Signature" => '',
                'Actual Patient Discharge TAT Duration' => '',
                'Remarks' => 'No ward discharge TAT records in this period.',
            ];
        }

        $lt6 = 0;
        $gt6 = 0;
        foreach ($detailRows as $row) {
            if ((int) $row['actual_discharge_tat_minutes'] < 360) {
                $lt6++;
            } else {
                $gt6++;
            }
        }
        $total = count($detailRows);
        return ['title' => 'Ward Discharge Turnaround Time Report', 'summary' => [
            'Total TAT Records' => $total,
            'Discharge Time < 6 Hours' => $lt6,
            'Discharge Time > 6 Hours' => $gt6,
            'Accomplishment Percentage' => nrPct($lt6, $total),
        ], 'columns' => array_keys($rows[0]), 'rows' => $rows];
    }

    if ($key === 'or_safe') {
        $rows = [];
        foreach (['Elective','Emergency'] as $type) {
            $genTotal = nrScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type=? AND schedule_date_time BETWEEN ? AND ?", [$type, $start, $end], 'sss');
            $genDone = nrScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type=? AND status IN ('COMPLETED','DONE') AND schedule_date_time BETWEEN ? AND ?", [$type, $start, $end], 'sss');
            $rows[] = ['OR Schedule Type'=>$type,'General Surgery Total'=>$genTotal,'General Surgery Done'=>$genDone,'General Surgery Pending'=>max(0, $genTotal-$genDone),'OB/GYNE Total'=>0,'OB/GYNE Done'=>0,'OB/GYNE Pending'=>0,'Remarks'=>''];
        }
        return ['title' => 'Safe Operation / OR Schedule Report', 'summary' => [], 'columns' => array_keys($rows[0]), 'rows' => $rows];
    }

    $totalAdmissions = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE admission_date BETWEEN ? AND ?", $rangeParams, 'ss');
    $totalDischarges = nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('discharge','discharged') AND updated_at BETWEEN ? AND ?", $rangeParams, 'ss');
    $rows = [
        ['Metric'=>'Total admissions','Value'=>$totalAdmissions],
        ['Metric'=>'Total discharges','Value'=>$totalDischarges],
        ['Metric'=>'Total deaths','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('death','er_death','expired','doa') AND updated_at BETWEEN ? AND ?", $rangeParams, 'ss')],
        ['Metric'=>'Total transfers in','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE created_at BETWEEN ? AND ?", $rangeParams, 'ss')],
        ['Metric'=>'Total transfers out','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE created_at BETWEEN ? AND ?", $rangeParams, 'ss')],
        ['Metric'=>'Total ER consultations','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE arrival_time BETWEEN ? AND ?", $rangeParams, 'ss')],
        ['Metric'=>'Total pending admissions','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM patients WHERE status='for_admission'")],
        ['Metric'=>'OR elective cases','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type='Elective' AND schedule_date_time BETWEEN ? AND ?", $rangeParams, 'ss')],
        ['Metric'=>'OR emergency cases','Value'=>nrScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type='Emergency' AND schedule_date_time BETWEEN ? AND ?", $rangeParams, 'ss')],
    ];
    return ['title' => 'Monthly Nursing Report', 'summary' => [], 'columns' => ['Metric','Value'], 'rows' => $rows];
}

function nursingReportErExtra(mysqli $conn, string $start, string $end): array {
    $triageRows = [];
    foreach (['Emergent' => ['red','emergency'], 'Urgent' => ['yellow','urgent'], 'Non-Urgent' => ['green','non-urgent']] as $label => $levels) {
        $placeholders = implode(',', array_fill(0, count($levels), '?'));
        $params = array_merge($levels, [$start, $end]);
        $types = str_repeat('s', count($levels)) . 'ss';
        $total = nrScalar($conn, "SELECT COUNT(*) FROM triage WHERE triage_level IN ($placeholders) AND triage_time BETWEEN ? AND ?", $params, $types);
        $triageRows[] = ['Triage Category'=>$label,'Turnaround Time < 4 Hours'=>$total,'Turnaround Time > 4 Hours'=>0,'Total'=>$total];
    }
    $caseI = ['Acute Flaccid Paralysis / Suspected Polio','Adverse Events Following Immunization / AEFI','Anthrax','Human Avian Influenza','Measles','Meningococcal Disease','Neonatal Tetanus','Paralytic Shellfish Poisoning','Rabies','Severe Acute Respiratory Syndrome / SARS'];
    $caseII = ['Acute Bloody Diarrhea','Acute Encephalitis Syndrome','Acute Hemorrhagic Fever Syndrome','Acute Viral Hepatitis','Bacterial Meningitis','Cholera','Dengue','Diphtheria','Influenza-like Illness','Japanese Encephalitis','Leptospirosis','Malaria','Non-Neonatal Tetanus','Pertussis / Whooping Cough','Rotavirus','Typhoid and Paratyphoid Fever','Zika'];
    $caseRows = fn($cases) => array_map(fn($case) => ['Case Name'=>$case,'Number of Cases'=>0,'Date Reported'=>'','Department'=>'ER','Remarks'=>''], $cases);
    return [
        ['title' => 'Triage Category', 'columns' => ['Triage Category','Turnaround Time < 4 Hours','Turnaround Time > 4 Hours','Total'], 'rows' => $triageRows],
        ['title' => 'Category I Cases - Immediately Notifiable', 'columns' => ['Case Name','Number of Cases','Date Reported','Department','Remarks'], 'rows' => $caseRows($caseI)],
        ['title' => 'Category II Cases - Weekly Notifiable', 'columns' => ['Case Name','Number of Cases','Date Reported','Department','Remarks'], 'rows' => $caseRows($caseII)],
    ];
}
