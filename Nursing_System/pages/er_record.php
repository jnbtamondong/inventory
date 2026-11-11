<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/patient-registration.php';
require_once '../includes/er-record-pdf.php';

requireLogin();
requirePermission('patients','print','ER');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');exit('Read-only patient record.');}

$patient_id = filter_var($_GET['id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0;
if (!$patient_id) {
    header('Location: patients.php');
    exit();
}
if(!erRecordCanAccess($conn,$patient_id)){http_response_code(403);exit('ER record access is not permitted for this encounter.');}
$hasRegistration=registrationReady($conn);
$registrationFields=$hasRegistration?"r.chief_complaint registered_complaint,r.medicolegal registered_medicolegal,r.ip registered_ip,r.coordinated registered_coordinated,r.referred_by registered_referral,CONCAT(ru.first_name,' ',ru.last_name) registered_by_name,":"";
$registrationJoins=$hasRegistration?'LEFT JOIN nis_registrations r ON r.patient_id=p.patient_id LEFT JOIN users ru ON ru.user_id=r.registered_by':'';

$stmt = $conn->prepare("
    SELECT
        p.*,
        $registrationFields
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        COALESCE(p.province_name, pr.province_name) AS display_province_name,
        COALESCE(p.municipality_name, m.municipality_name) AS display_municipality_name,
        COALESCE(p.barangay_name, br.barangay_name) AS display_barangay_name,
        b.bed_number,
        b.room_number,
        t.triage_level,
        t.chief_complaint,
        t.assessment,
        t.medicolegal,
        t.ip,
        t.referred_by,
        t.coordinated,
        t.vital_signs,
        t.vital_pr,
        t.vital_height,
        t.vital_weight,
        t.vital_spo2,
        t.vital_cbg,
        t.triage_time AS assessment_time,
        CONCAT(u.first_name, ' ', u.last_name) AS triaged_by
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    LEFT JOIN provinces pr ON p.province_id = pr.province_id
    LEFT JOIN municipalities m ON p.municipality_id = m.municipality_id
    LEFT JOIN barangays br ON p.barangay_id = br.barangay_id
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    LEFT JOIN users u ON t.assigned_by = u.user_id
    $registrationJoins
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header('Location: patients.php');
    exit();
}
if(empty($patient['assessment_time'])&&$hasRegistration){
    foreach(['chief_complaint'=>'registered_complaint','medicolegal'=>'registered_medicolegal','ip'=>'registered_ip','coordinated'=>'registered_coordinated','referred_by'=>'registered_referral'] as $field=>$source)$patient[$field]=$patient[$source]??'';
}
$disposition=registrationQuery($conn,"SELECT status_ended_at FROM patient_tracking WHERE patient_id=? AND action='Status Updated' AND to_status=? ORDER BY tracking_id DESC LIMIT 1",[$patient_id,$patient['status']])->get_result()->fetch_assoc();
$isDisposition=in_array($patient['status'],array_merge(array_keys(patientDispositionOptions()),['admit_inpatient','discharged','expired','death','trans_out','trans_in']),true);
$dispositionTime=$isDisposition&&!empty($disposition['status_ended_at'])?date('m/d/Y H:i',strtotime($disposition['status_ended_at'])):'';
$finalDiagnosis=trim(($patient['final_diagnosis']??'').(!empty($patient['specific_diagnosis'])?' - '.$patient['specific_diagnosis']:''));
$download=($_GET['download']??'')==='pdf';
$backUrl=nisTrackerCanOpenChart($conn,$patient_id)?'patient_detail.php?id='.$patient_id:'patients.php?module=er';

$vitals = [];
if (!empty($patient['vital_signs'])) {
    $decodedVitals = json_decode($patient['vital_signs'], true);
    if (is_array($decodedVitals)) {
        $vitals = $decodedVitals;
    }
}

$address = trim(($patient['house_no_street'] ?? '') . ', ' . ($patient['display_barangay_name'] ?? '') . ', ' . ($patient['display_municipality_name'] ?? '') . ', ' . ($patient['display_province_name'] ?? ''), ', ');
$arrivalDate = !empty($patient['triage_date']) ? date('m/d/Y', strtotime($patient['triage_date'])) : '';
$arrivalTime = !empty($patient['triage_time']) ? substr($patient['triage_time'], 0, 5) : '';
$triageTime = !empty($patient['assessment_time']) ? date('H:i', strtotime($patient['assessment_time'])) : '';
$lastName = $patient['last_name'] ?? '';
$firstName = $patient['first_name'] ?? '';
$middleName = $patient['middle_name'] ?? '';
$registrationUser = trim($patient['registered_by_name']??'');
$consentName = trim(($firstName ?? '') . ' ' . ($lastName ?? '') . ' ' . ($middleName ?? ''));
$consentName = $consentName !== '' ? $consentName : ($patient['patient_name'] ?? '');
$consentStatement = '<p class="consent-copy"><span class="consent-open">Ako si <span class="consent-name">' . htmlspecialchars($consentName) . '</span></span> ay humihiling/nakikiusap na magawa sa akin ang kinakailangang pagsusuri upang matiyak ang aking sakit at ito\'y malapatan ng wastong lunas o anumang operasyon. Bilang pasyente ipinapaalam ko sa lahat na walang pananagutan ang manggagamot ng pagamutang ito sa anumang kumplikasyon o pangyayari na sanhi ng pamamaraang medical na kinakailangang gawin sa akin. Bilang pasyente, susunod at ipasusunod ko ang mga patakaran ng ospital. Sa harapan ng aking saksi ay inilagda ko ang pangalan ko bilang patunay sa nasabing kasulatan.</p>';

$erVitals = [
    'bp' => $vitals['blood_pressure'] ?? '',
    'pr' => ($patient['vital_pr'] ?? '') ?: (($vitals['pr'] ?? '') ?: ($vitals['heart_rate'] ?? '')),
    'rr' => $vitals['respiratory_rate'] ?? '',
    't' => $vitals['temperature'] ?? '',
    'spo2' => $patient['vital_spo2'] ?? ($vitals['spo2'] ?? ''),
    'wt' => $patient['vital_weight'] ?? ($vitals['weight'] ?? ''),
    'ht' => $patient['vital_height'] ?? ($vitals['height'] ?? ''),
    'cbg' => $patient['vital_cbg'] ?? ($vitals['cbg'] ?? '')
];

function erText($value) {
    return trim((string) ($value ?? ''));
}

function erCell($colStart, $rowStart, $colEnd, $rowEnd, $content, $classes = '') {
    return '<div class="xl-cell ' . htmlspecialchars($classes) . '" style="grid-column:' . (int) $colStart . ' / ' . ((int) $colEnd + 1) . ';grid-row:' . (int) $rowStart . ' / ' . ((int) $rowEnd + 1) . ';">' . $content . '</div>';
}

function erBorder($colStart, $rowStart, $colEnd, $rowEnd, $classes = 'xl-border') {
    return erCell($colStart, $rowStart, $colEnd, $rowEnd, '', $classes);
}

function erValue($value, $classes = '') {
    return '<span class="xl-value ' . htmlspecialchars($classes) . '">' . htmlspecialchars(erText($value)) . '</span>';
}

function erCheck($checked) {
    return '<span class="xl-check">' . ($checked ? 'X' : '') . '</span>';
}

function erLabel($text) {
    return '<span class="xl-label">' . htmlspecialchars($text) . '</span>';
}

function erVitalItem($label, $value, $class = '') {
    return '<span class="vital-item ' . htmlspecialchars($class) . '"><strong>' . htmlspecialchars($label) . '</strong><span class="vital-input">' . htmlspecialchars(erText($value)) . '</span></span>';
}
$erContinuation=[];
function erRecordFieldText(string $label,$value,int $limit=100): string {
    global $erContinuation;
    $text=trim((string)($value??''));
    if(mb_strlen($text)>$limit){$erContinuation[$label]=$text;return 'See continuation sheet.';}
    return $text;
}
if($download)ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ER Record - <?php echo htmlspecialchars($patient['patient_name']); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --page-width: min(8.5in, calc(100vw - 28px));
            --page-height: calc(var(--page-width) * 13 / 8.5);
            --page-pad-top: calc(var(--page-height) * 0.0185);
            --page-pad-x: calc(var(--page-width) * 0.014);
            --page-pad-bottom: calc(var(--page-height) * 0.014);
            --footer-height: calc(var(--page-height) * 0.044);
            --footer-gap: 0;
            --sheet-height: calc(var(--page-height) - var(--page-pad-top) - var(--page-pad-bottom) - var(--footer-height) - var(--footer-gap));
        }

        body {
            margin: 0;
            background: #e9eef2;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            width: var(--page-width);
            margin: 18px auto;
        }

        .er-page {
            position: relative;
            display: flex;
            flex-direction: column;
            width: var(--page-width);
            min-height: var(--page-height);
            height: var(--page-height);
            margin: 0 auto 24px;
            padding: var(--page-pad-top) var(--page-pad-x) var(--page-pad-bottom);
            background: #fff;
            border: 1px solid #9aa4ad;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
        }

        .excel-sheet {
            --u: 0.575pt;
            display: grid;
            grid-template-columns:
                1.2fr 22fr 14.2fr 6.2fr 16.5fr 17.5fr 9.5fr 5.5fr 13.8fr 12.2fr
                12.4fr 11fr 23fr 7.8fr 5.6fr 8.2fr 6.8fr 15.6fr 8.4fr;
            grid-template-rows:
                minmax(19px, max-content) minmax(18px, max-content) minmax(16px, max-content) minmax(14px, max-content)
                minmax(3px, max-content) minmax(21px, max-content) minmax(10px, max-content) minmax(20px, max-content)
                minmax(16px, max-content) minmax(30px, max-content) minmax(16px, max-content)
                repeat(5, minmax(11px, max-content))
                repeat(5, minmax(16px, max-content))
                minmax(11px, max-content)
                minmax(16px, max-content) minmax(15px, max-content)
                repeat(7, minmax(13px, max-content))
                minmax(15px, max-content)
                repeat(24, minmax(16px, max-content))
                repeat(10, minmax(16px, max-content))
                minmax(6px, max-content)
                repeat(5, minmax(14px, max-content));
            width: 100%;
            height: var(--sheet-height);
            min-height: 0;
            flex: 0 0 var(--sheet-height);
            position: relative;
        }

        .xl-cell {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 0;
            min-height: 0;
            padding: 1px 3px;
            line-height: 1.15;
            overflow: visible;
            z-index: 1;
            overflow-wrap: normal;
            word-break: normal;
        }

        .xl-border {
            border: 1px solid #000;
            z-index: 0;
            pointer-events: none;
        }

        .xl-border-top {
            border-top: 1px solid #000;
            z-index: 0;
            pointer-events: none;
        }

        .xl-border-bottom {
            border-bottom: 1px solid #000;
            z-index: 0;
            pointer-events: none;
        }

        .xl-border-left {
            border-left: 1px solid #000;
            z-index: 0;
            pointer-events: none;
        }

        .xl-border-right {
            border-right: 1px solid #000;
            z-index: 0;
            pointer-events: none;
        }

        .center {
            justify-content: center;
            text-align: center;
        }

        .right {
            justify-content: flex-end;
            text-align: right;
        }

        .bold,
        .xl-label {
            font-weight: 700;
            font-size: 10px;
            overflow-wrap: anywhere;
        }

        .title-main {
            font-size: 13px;
            font-weight: 700;
        }

        .title-hospital {
            font-size: 14px;
            font-weight: 700;
        }

        .title-address {
            font-size: 14px;
        }

        .record-title {
            font-size: 20px;
            font-weight: 700;
            text-decoration: underline;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            justify-content: center;
            text-align: center;
        }

        .xl-logo {
            width: .8in;
            height: .8in;
            object-fit: contain;
            justify-self: center;
            align-self: center;
        }

        .xl-check {
            display: inline-flex;
            width: 11px;
            height: 11px;
            border: 1px solid #000;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }

        .xl-value {
            display: block;
            width: 100%;
            min-height: 16px;
            border-bottom: 1px solid #000;
            padding: 0 2px;
            font-weight: 400;
            overflow: visible;
            text-overflow: clip;
            white-space: normal;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .xl-value.wrap {
            white-space: normal;
            overflow: visible;
            line-height: 1.15;
        }

        .box-area {
            align-items: flex-start;
            border: 1px solid #000;
            padding: 4px;
            white-space: pre-wrap;
            line-height: 1.15;
            overflow: visible;
            overflow-wrap: anywhere;
        }

        .line-top {
            border-top: 1px solid #000;
        }

        .line-bottom {
            border-bottom: 1px solid #000;
        }

        .tiny {
            font-size: 10px;
        }

        .consent-text {
            text-align: justify;
        }

        .consent-paragraph {
            display: block;
            padding: 3px 18px;
            text-align: center;
            white-space: normal;
            overflow-wrap: normal;
            overflow: hidden;
        }

        .consent-copy {
            margin: 0 auto;
            max-width: 6.8in;
            font-size: 9px;
            line-height: 1.15;
            text-align: center;
        }

        .consent-open {
            white-space: nowrap;
        }

        .consent-name {
            display: inline-block;
            min-width: 170px;
            padding: 0 10px;
            border-bottom: 1px solid #000;
            line-height: 1.05;
            white-space: nowrap;
            vertical-align: baseline;
        }

        .bottom-consent {
            display: block;
            padding: 2px 8px;
            line-height: 1.35;
            text-align: left;
            white-space: normal;
        }

        .bottom-consent-name {
            display: inline-block;
            min-width: 210px;
            padding: 0 8px;
            border-bottom: 1px solid #000;
            line-height: 1.05;
            white-space: nowrap;
            vertical-align: baseline;
        }

        .nowrap {
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
            overflow: hidden;
        }

        .nowrap .xl-label {
            overflow-wrap: normal;
            white-space: nowrap;
        }

        .inline-record-label {
            flex-wrap: nowrap;
            gap: 4px;
        }

        .inline-record-label .xl-label {
            flex: 0 0 auto;
            white-space: nowrap;
            overflow-wrap: normal;
        }

        .inline-record-label .xl-value {
            flex: 1 1 auto;
            min-width: 0;
        }

        .transfer-date-label {
            justify-content: flex-start;
            text-align: left;
            padding-left: 5px;
        }

        .doctor-signature-label {
            justify-content: flex-start;
            text-align: left;
            padding-left: 8px;
            font-size: 10px;
        }

        .bottom-signature-caption {
            align-items: flex-start;
            padding-top: 3px;
            line-height: 1.05;
        }

        .top-label {
            font-size: 10px;
            line-height: 1;
        }

        .vital-label {
            padding-left: 2px;
            padding-right: 1px;
            white-space: nowrap;
            overflow: visible;
        }

        .vital-label .xl-label {
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
        }

        .vital-value {
            padding-left: 1px;
            padding-right: 3px;
        }

        .vital-value .xl-value {
            min-height: 11px;
            padding: 0 1px;
            text-align: center;
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
        }

        .vitals-line {
            display: grid;
            grid-template-columns: 1.28fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
            column-gap: 12px;
            align-items: center;
            padding: 1px 0;
            overflow: hidden;
        }

        .vital-item {
            display: grid;
            grid-template-columns: max-content minmax(26px, 1fr);
            column-gap: 8px;
            align-items: baseline;
            min-width: 0;
            white-space: nowrap;
        }

        .vital-item strong {
            font-weight: 700;
            line-height: 1;
        }

        .vital-input {
            display: block;
            min-height: 12px;
            padding: 0 3px;
            border-bottom: 1px solid #000;
            text-align: center;
            line-height: 1.05;
            overflow: hidden;
            text-overflow: clip;
        }

        .vital-item.bp {
            grid-template-columns: max-content minmax(52px, 1fr);
        }

        .name-line {
            display: block;
            width: 100%;
            min-height: 12px;
            padding: 0 3px;
            border-bottom: 1px solid #000;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
        }

        .name-line .name-part {
            display: inline-block;
            width: 33.333%;
            padding: 0 3px;
            text-align: left;
            vertical-align: bottom;
        }

        .check-label {
            gap: 4px;
            justify-content: flex-start;
            padding-left: 5px;
            padding-right: 2px;
            line-height: 1.05;
            font-size: 10px;
            white-space: nowrap;
            align-items: flex-start;
            padding-top: 2px;
            padding-bottom: 2px;
            overflow: hidden;
            text-align: left;
        }

        .disposition-option {
            justify-content: flex-start;
            text-align: left;
            padding-left: 5px;
        }

        .check-label .xl-check {
            margin-left: 0;
            margin-right: 2px;
            flex: 0 0 auto;
        }

        .er-footer {
            flex: 0 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-family: "Times New Roman", Times, serif;
            color: #000;
            margin-top: auto;
            border-top: 1px solid #000;
            padding-top: 6px;
            min-height: var(--footer-height);
            z-index: 5;
        }

        .privacy-notice {
            width: 2.95in;
            border: 1px solid #000;
            padding: 9px 22px 8px;
            text-align: center;
            font-size: 6px;
            line-height: 1.08;
        }

        .privacy-notice strong {
            font-weight: 700;
        }

        .revision-table {
            margin-top: 0.35in;
            border-collapse: collapse;
            font-size: 6px;
            line-height: 1;
            font-weight: 700;
        }

        .revision-table td {
            border: 1px solid #000;
            padding: 3px 8px;
            min-width: 0.48in;
            white-space: nowrap;
        }

        .revision-table td:last-child {
            min-width: 0.52in;
            text-align: center;
        }
        .er-continuation {width:var(--page-width);margin:24px auto;padding:0.4in;background:white;line-height:1.5;}
        .er-continuation p {white-space:pre-wrap;overflow-wrap:anywhere;}

        @media print {
            @page {
                size: 8.5in 13in;
                margin: 0;
            }

            :root {
                --page-width: 8.5in;
                --page-height: 13in;
                --page-pad-top: 0.10in;
                --page-pad-x: 0.08in;
                --page-pad-bottom: 0.08in;
                --footer-height: 0.70in;
                --footer-gap: 0;
                --sheet-height: 12.12in;
            }

            html {
                width: var(--page-width);
                height: var(--page-height);
                overflow: hidden !important;
                scrollbar-width: none;
            }

            body {
                background: #fff;
                width: var(--page-width);
                height: var(--page-height);
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                scrollbar-width: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body::-webkit-scrollbar,
            .er-page::-webkit-scrollbar {
                display: none;
            }

            .print-actions {
                display: none;
            }

            .er-page {
                display: flex !important;
                flex-direction: column !important;
                position: relative;
                width: var(--page-width);
                min-height: var(--page-height);
                height: var(--page-height);
                margin: 0 !important;
                padding: var(--page-pad-top) var(--page-pad-x) var(--page-pad-bottom);
                border: 0;
                box-shadow: none;
                overflow: hidden;
                background: #fff;
                transform: none !important;
            }

            .excel-sheet {
                display: grid !important;
                grid-template-rows:
                    minmax(21px, max-content) minmax(19px, max-content) minmax(17px, max-content) minmax(15px, max-content)
                    minmax(3px, max-content) minmax(23px, max-content) minmax(11px, max-content) minmax(22px, max-content)
                    minmax(17px, max-content) minmax(32px, max-content) minmax(17px, max-content)
                    repeat(5, minmax(12px, max-content))
                    repeat(5, minmax(17px, max-content))
                    minmax(12px, max-content)
                    minmax(17px, max-content) minmax(16px, max-content)
                    repeat(7, minmax(14px, max-content))
                    minmax(16px, max-content)
                    repeat(24, minmax(17px, max-content))
                    repeat(10, minmax(17px, max-content))
                    minmax(6px, max-content)
                    repeat(5, minmax(15px, max-content));
                width: 100%;
                height: var(--sheet-height);
                min-height: 0;
                flex: 0 0 var(--sheet-height);
                transform: none;
                align-content: stretch;
            }

            .er-footer {
                display: flex !important;
                position: static;
                height: var(--footer-height);
                min-height: var(--footer-height);
                margin-top: var(--footer-gap);
            }
            .er-footer .revision-table {margin-top:0;align-self:flex-end;}
            html:has(.er-continuation),html:has(.er-continuation) body {height:auto!important;overflow:visible!important;}
            html:has(.er-continuation) .er-page {break-after:page;}
            .er-continuation {margin:0;width:8.5in;min-height:13in;padding:0.4in;break-before:page;}
        }
    </style>
    <link rel="stylesheet" href="../public/css/print-controls.css?v=1">
</head>
<body class="ns-print-preview">
    <div class="print-actions">
        <a href="<?=h($backUrl)?>" class="btn btn-secondary">Back</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print ER Record</button>
        <a href="er_record.php?id=<?=(int)$patient_id?>&amp;download=pdf" class="btn btn-primary">Download PDF</a>
    </div>

    <main class="er-page">
        <div class="excel-sheet">
            <?php
            echo erCell(3, 1, 5, 4, '<img class="xl-logo" src="../public/images/branding/er_record_left.png" alt="">', 'center');
            echo erCell(14, 1, 17, 4, '<img class="xl-logo" src="../public/images/branding/er_record_right.png" alt="">', 'center');
            echo erCell(6, 1, 13, 1, 'DEPARTMENT OF HEALTH', 'center title-main');
            echo erCell(6, 2, 13, 2, 'JONI VILLANUEVA GENERAL HOSPITAL', 'center title-hospital');
            echo erCell(6, 3, 13, 3, 'National Road, Igulot, Bocaue, Bulacan 3018', 'center title-address');
            echo erCell(2, 6, 19, 6, 'EMERGENCY ROOM RECORD', 'center record-title');

            echo erCell(2, 8, 3, 8, erLabel('TRIAGE ACUITY SCALE:'));
            echo erCell(4, 8, 4, 8, erCheck(($patient['triage_level'] ?? '') === 'red'), 'center');
            echo erCell(5, 8, 5, 8, 'EMERGENT', 'bold nowrap top-label');
            echo erCell(6, 8, 6, 8, erCheck(($patient['triage_level'] ?? '') === 'yellow'), 'center');
            echo erCell(7, 8, 8, 8, 'URGENT', 'bold nowrap top-label');
            echo erCell(9, 8, 9, 8, erCheck(($patient['triage_level'] ?? '') === 'green'), 'center');
            echo erCell(10, 8, 11, 8, 'NON-URGENT', 'bold nowrap top-label');
            echo erCell(12, 8, 13, 8, erLabel('MEDICOLEGAL:'));
            echo erCell(14, 8, 14, 8, erCheck(!empty($patient['medicolegal'])), 'center');
            echo erCell(15, 8, 16, 8, 'YES', 'bold');
            echo erCell(17, 8, 17, 8, erCheck(empty($patient['medicolegal'])), 'center');
            echo erCell(18, 8, 18, 8, 'NO', 'bold');
            echo erCell(2, 9, 3, 9, erLabel('DATE OF REGISTRATION:'));
            echo erCell(4, 9, 10, 9, erValue($arrivalDate));
            echo erCell(11, 9, 13, 9, erLabel('REGISTERED BY:'), 'nowrap top-label');
            echo erCell(14, 9, 18, 9, erValue($registrationUser));
            echo erCell(2, 10, 3, 10, erLabel('TIME OF ARRIVAL:'));
            echo erCell(4, 10, 10, 10, erValue($arrivalTime));
            echo erCell(11, 10, 13, 10, erLabel('TIME OF TRIAGE:'), 'nowrap top-label');
            echo erCell(14, 10, 18, 10, erValue($triageTime));

            echo erCell(2, 11, 19, 11, 'PAHINTULOT / PAKIUSAP / PATAKARAN SA PAGPAPAOSPITAL', 'section-title');
            echo erCell(2, 12, 19, 15, $consentStatement, 'consent-paragraph');
            echo erCell(3, 17, 3, 17, 'Saksi:', 'bold');
            echo erCell(4, 17, 9, 17, erValue(''));
            echo erCell(12, 17, 17, 17, erValue(''));
            echo erCell(4, 18, 9, 18, 'Lagda sa ibabaw ng buong Pangalan', 'center tiny');
            echo erCell(12, 18, 17, 18, 'Pasyente (Lagda sa ibabaw ng Pangalan)', 'center tiny');
            echo erCell(4, 20, 9, 20, erValue(''));
            echo erCell(4, 21, 9, 21, 'Kaugnayan sa Pasyente', 'center tiny');

            echo erCell(2, 23, 19, 23, 'PATIENT INFORMATION', 'section-title');
            echo erCell(2, 24, 3, 24, erLabel('PATIENT NAME:'));
            echo erCell(4, 24, 15, 24, '<span class="name-line"><span class="name-part">' . htmlspecialchars($lastName) . '</span><span class="name-part">' . htmlspecialchars($firstName) . '</span><span class="name-part">' . htmlspecialchars($middleName) . '</span></span>');
            echo erCell(16, 24, 16, 24, erLabel('AGE:'), 'nowrap');
            echo erCell(17, 24, 19, 24, erValue($patient['age'] ?? ''));
            echo erCell(4, 25, 6, 25, 'LAST NAME', 'center tiny');
            echo erCell(7, 25, 11, 25, 'FIRST NAME', 'center tiny');
            echo erCell(12, 25, 15, 25, 'MIDDLE NAME', 'center tiny');
            echo erCell(2, 26, 2, 26, erLabel('BIRTHDATE:'), 'nowrap');
            echo erCell(3, 26, 6, 26, erValue(formatPatientDate($patient['date_of_birth'] ?? null)));
            echo erCell(7, 26, 16, 26, erLabel('CONTACT NUMBER:') . erValue($patient['phone'] ?? ''), 'inline-record-label');
            echo erCell(18, 26, 19, 26, erCheck(!empty($patient['ip'])) . ' IP', 'bold check-label');
            echo erCell(2, 27, 2, 27, erLabel('RELIGION:'));
            echo erCell(3, 27, 6, 27, erValue($patient['religion'] ?? ''));
            echo erCell(7, 27, 12, 27, erLabel('GENDER:') . erValue($patient['gender'] ?? ''), 'inline-record-label');
            echo erCell(13, 27, 13, 27, erLabel('CIVIL STATUS:'));
            echo erCell(14, 27, 19, 27, erValue($patient['civil_status'] ?? ''));
            echo erCell(2, 28, 2, 28, erLabel('ADDRESS:'));
            echo erCell(3, 28, 19, 28, erValue(erRecordFieldText('Address',$address)));
            echo erCell(2, 29, 3, 29, erLabel('REFERRED BY:'), 'nowrap');
            echo erCell(4, 29, 12, 29, erValue($patient['referred_by'] ?? ''));
            echo erCell(13, 29, 14, 29, erLabel('COORDINATED:'));
            echo erCell(15, 29, 15, 29, erCheck(!empty($patient['coordinated'])), 'center');
            echo erCell(16, 29, 16, 29, 'YES', 'bold');
            echo erCell(17, 29, 17, 29, erCheck(empty($patient['coordinated'])), 'center');
            echo erCell(18, 29, 18, 29, 'NO', 'bold');
            echo erCell(2, 30, 3, 30, erLabel('CHIEF COMPLAINT:'));
            echo erCell(4, 30, 19, 30, erValue(erRecordFieldText('Chief Complaint',$patient['chief_complaint'] ?? '')));
            echo erCell(2, 32, 19, 32,
                erVitalItem('BP:', $erVitals['bp'], 'bp') .
                erVitalItem('PR:', $erVitals['pr']) .
                erVitalItem('RR:', $erVitals['rr']) .
                erVitalItem('T:', $erVitals['t']) .
                erVitalItem('SPO2:', $erVitals['spo2']) .
                erVitalItem('WT:', $erVitals['wt']) .
                erVitalItem('HT:', $erVitals['ht']) .
                erVitalItem('CBG:', $erVitals['cbg']),
                'vitals-line'
            );

            echo erBorder(2, 33, 14, 56, 'xl-border');
            echo erBorder(15, 33, 19, 56, 'xl-border');
            echo erBorder(2, 33, 19, 33, 'xl-border-bottom');
            echo erBorder(2, 56, 19, 56, 'xl-border-bottom');
            echo erBorder(4, 34, 4, 56, 'xl-border-left');
            echo erBorder(15, 33, 15, 56, 'xl-border-left');
            echo erBorder(2, 34, 14, 34, 'xl-border-bottom');
            echo erBorder(2, 37, 14, 37, 'xl-border-bottom');
            echo erBorder(2, 40, 14, 40, 'xl-border-bottom');
            echo erBorder(2, 43, 14, 43, 'xl-border-bottom');
            echo erBorder(2, 46, 14, 46, 'xl-border-bottom');
            echo erBorder(2, 48, 14, 48, 'xl-border-bottom');
            echo erBorder(2, 52, 14, 52, 'xl-border-bottom');
            echo erBorder(2, 55, 14, 55, 'xl-border-bottom');
            echo erBorder(15, 40, 19, 40, 'xl-border-top xl-border-bottom');
            echo erBorder(15, 48, 19, 48, 'xl-border-top xl-border-bottom');
            echo erCell(2, 33, 5, 33, 'PHYSICAL EXAMINATION:', 'bold');
            echo erCell(15, 33, 19, 33, 'HISTORY OF PRESENT ILLNESS', 'center bold');
            echo erCell(15, 34, 19, 39, htmlspecialchars(erRecordFieldText('Assessment',$patient['assessment'] ?? '')), 'box-area');
            echo erCell(15, 40, 19, 40, 'PAST MEDICAL HISTORY', 'center bold');
            echo erCell(15, 41, 19, 47, htmlspecialchars(erRecordFieldText('Medical History',$patient['medical_history'] ?? '',220)), 'box-area');
            echo erCell(15, 48, 19, 48, 'FAMILY/PERSONAL HISTORY', 'center bold');
            echo erCell(15, 49, 19, 56, '', 'box-area');

            $examRows = [
                [2,34,3,34,'General Survey:', 4,34,7,34,'Awake and Alert', 8,34,14,34,'Alert and Sensorium'],
                [2,35,3,37,'HEENT:', 4,35,7,35,'Essentially normal', 8,35,11,35,'Abnormal pupillary reaction', 12,35,14,35,'Cervical lymphadenopathy'],
                [4,36,7,36,'Dry mucous membrane', 8,36,11,36,'Icteric sclerae', 12,36,14,36,'Pale conjunctivae'],
                [4,37,7,37,'Sunken fontanelle', 8,37,11,37,'Others:', 12,37,14,37,'Sunken eyeballs'],
                [2,38,3,40,'CHEST/LUNGS:', 4,38,7,38,'Essentially normal', 8,38,11,38,'Asymmetrical chest expansion', 12,38,14,38,'Decreased breath sounds'],
                [4,39,7,39,'Wheezes', 8,39,11,39,'Lump/s over breast(s)', 12,39,14,39,'Rales/Crackles/rhonchi'],
                [4,40,9,40,'Intercostal rib / clavicular retraction', 10,40,14,40,'Others:'],
                [2,41,3,43,'CVS:', 4,41,7,41,'Essentially normal', 8,41,11,41,'Displaced apex beat', 12,41,14,41,'Heaves and/or thrills'],
                [4,42,7,42,'Pericardial bulge', 8,42,11,42,'Irregular rhythm', 12,42,14,42,'Muffled heart sounds'],
                [4,43,7,43,'Murmur', 8,43,14,43,'Others:'],
                [2,44,3,46,'ABDOMEN:', 4,44,7,44,'Essentially normal', 8,44,11,44,'Abdominal rigidity', 12,44,14,44,'Abdomen tenderness'],
                [4,45,7,45,'Hyperactive bowel sounds'],
                [4,46,7,46,'Uterine contraction', 8,46,14,46,'Others:'],
                [2,47,3,48,'GU (I E):', 4,47,7,47,'Essentially normal', 8,47,11,47,'Blood stained in exam finger'],
                [4,48,7,48,'Presence of abnormal discharge', 8,48,11,48,'Cervical dilation', 12,48,14,48,'Others:'],
                [2,49,3,52,'SKIN / EXTREMITIES', 4,49,7,49,'Essentially normal', 8,49,11,49,'Clubbing', 12,49,14,49,'Cold clammy skin'],
                [4,50,7,50,'Edema/swelling', 8,50,11,50,'Decreased mobility', 12,50,14,50,'Pale nailbeds'],
                [4,51,7,51,'Rashes/petechiae', 8,51,11,51,'Weak pulses', 12,51,14,51,'Cyanosis/mottled skin'],
                [4,52,7,52,'Poor skin turgor', 8,52,14,52,'Others:'],
                [2,53,3,55,'NEURO-EXAM:', 4,53,7,53,'Essentially normal', 8,53,11,53,'Abnormal gait', 12,53,14,53,'Abnormal position sense'],
                [4,54,7,54,'Abnormal/decreased sensation', 8,54,11,54,'Abnormal reflex(es)', 12,54,14,54,'Poor/altered memory'],
                [4,55,7,55,'Poor muscle tone/strength', 8,55,11,55,'Poor coordination', 12,55,14,55,'Others:']
            ];
            foreach ($examRows as $row) {
                for ($i = 0; $i < count($row); $i += 5) {
                    echo erCell($row[$i], $row[$i + 1], $row[$i + 2], $row[$i + 3], ($i === 0 && in_array($row[$i], [2], true) ? erLabel($row[$i + 4]) : erCheck(false) . ' ' . htmlspecialchars($row[$i + 4])), $i === 0 && $row[$i] === 2 ? 'bold' : 'check-label');
                }
            }

            echo erBorder(2, 57, 19, 66, 'xl-border');
            echo erBorder(2, 57, 19, 57, 'xl-border-bottom');
            echo erBorder(2, 58, 19, 58, 'xl-border-bottom');
            echo erBorder(2, 59, 19, 59, 'xl-border-bottom');
            echo erBorder(2, 60, 19, 60, 'xl-border-bottom');
            echo erBorder(2, 61, 19, 61, 'xl-border-bottom');
            echo erBorder(6, 59, 6, 61, 'xl-border-left');
            echo erBorder(13, 59, 13, 61, 'xl-border-left');
            echo erBorder(2, 62, 19, 62, 'xl-border-top xl-border-bottom');
            echo erBorder(2, 63, 19, 63, 'xl-border-bottom');
            echo erBorder(2, 64, 19, 64, 'xl-border-bottom');
            echo erBorder(11, 65, 11, 66, 'xl-border-left');
            echo erCell(2, 57, 19, 57, erLabel('INITIAL ASSESMENT:') . erValue(erRecordFieldText('Assessment',$patient['assessment'] ?? '')), 'inline-record-label');
            echo erCell(2, 58, 19, 58, 'DISPOSITION', 'section-title');
            echo erCell(2, 59, 5, 59, erCheck(($patient['status'] ?? '') === 'discharge') . ' TREATED AND SENT HOME', 'check-label disposition-option');
            echo erCell(6, 59, 12, 59, erCheck(in_array(($patient['status'] ?? ''), ['for_admission', 'admit_inpatient'], true)) . ' ADMISSION', 'check-label disposition-option');
            echo erCell(13, 59, 19, 59, erCheck(in_array(($patient['status'] ?? ''), ['ama', 'dama'], true)) . ' HAMA/DAMA', 'check-label disposition-option');
            echo erCell(2, 60, 5, 60, erCheck(($patient['status'] ?? '') === 'ama') . ' REFUSED ADMISSION', 'check-label disposition-option');
            echo erCell(6, 60, 12, 60, erCheck(($patient['status'] ?? '') === 'absconded') . ' ABSCONDED', 'check-label disposition-option');
            echo erCell(13, 60, 19, 60, erCheck(($patient['status'] ?? '') === 'doa') . ' DEAD ON ARRIVAL', 'check-label disposition-option');
            echo erCell(2, 61, 5, 61, erCheck(($patient['status'] ?? '') === 'er_death') . ' ER DEATH', 'check-label disposition-option');
            echo erCell(6, 61, 12, 61, erCheck(in_array(($patient['status'] ?? ''), ['referred', 'thoc'], true)) . ' TRANSFER TO HOSPITAL OF CHOICE:', 'check-label disposition-option');
            echo erCell(13, 61, 19, 61, erLabel('DATE & TIME OF TRANSFER:') . erValue(''), 'inline-record-label transfer-date-label');
            echo erCell(2, 62, 19, 62, erLabel('FINAL DIAGNOSIS:') . erValue(erRecordFieldText('Final Diagnosis',$finalDiagnosis)), 'inline-record-label');
            echo erCell(2, 63, 19, 63, erLabel('DATE & TIME OF DISPOSITION:') . erValue($dispositionTime), 'inline-record-label');
            echo erCell(2, 65, 10, 66, erLabel('DATE & TIME OF DISCHARGE:') . erValue(in_array($patient['status'],['discharge','discharged'],true)?$dispositionTime:''), 'inline-record-label');
            echo erCell(11, 65, 19, 66, 'NAME & SIGNATURE OF DOCTOR', 'bold line-top doctor-signature-label');
            echo erCell(
                2,
                68,
                19,
                69,
                'Ako si <span class="bottom-consent-name">' . htmlspecialchars($consentName) . '</span> ay nagpapatunay na ako ay nakita at na-eksamin ng doctor. Naipaliwanag sa akin ang aking kalagayan at plano nagamutan.',
                'bottom-consent'
            );
            echo erCell(3, 70, 3, 70, 'Saksi:', 'bold');
            echo erCell(4, 70, 6, 70, erValue(''));
            echo erCell(9, 70, 12, 70, erValue(''));
            echo erCell(14, 70, 18, 70, erValue(''));
            echo erCell(4, 71, 6, 71, 'Lagda sa ibabaw ng buong Pangalan', 'center tiny bottom-signature-caption');
            echo erCell(9, 71, 12, 71, 'Kaugnayan sa Pasyente', 'center tiny bottom-signature-caption');
            echo erCell(14, 71, 18, 71, 'Pasyente (Lagda sa ibabaw ng Pangalan)', 'center tiny bottom-signature-caption');
            ?>
        </div>
        <footer class="er-footer">
            <div class="privacy-notice">
                <strong>Data Privacy Notice:</strong> Bilang pagsunod sa RA 10173 o Data Privacy Act of 2012, ang <strong>Joni Villanueva General Hospital</strong> ay nangangako na protektahan ang lahat ng personal o maselang impormasyon na nakasulat. Kung may katanungan, maaaring sumulat sa <strong>jvgh.bulacan@gmail.com</strong> o mag-sadya sa Administration Office ng ospital, 1st Floor, JVGH, National Road, Igulot, Bocaue, Bulacan.
            </div>
            <table class="revision-table" aria-label="Revision information">
                <tr>
                    <td>Rev Number</td>
                    <td>00</td>
                </tr>
                <tr>
                    <td>Eff Date</td>
                    <td>09-18-25</td>
                </tr>
            </table>
        </footer>
    </main>
    <?php if($erContinuation):?><section class="er-continuation"><h1>Emergency Room Record - Continuation</h1><p><?=h($patient['patient_name'])?> | Hospital Number: <?=h($patient['permanent_patient_id']??'')?> | Encounter: <?=h($patient['patient_code'])?></p><?php foreach($erContinuation as $label=>$value):?><h2><?=h($label)?></h2><p><?=h($value)?></p><?php endforeach;?></section><?php endif;?>

    <?php if (isset($_GET['print']) && !$download): ?>
        <script>window.addEventListener('load', () => window.print());</script>
    <?php endif; ?>
</body>
</html>
<?php
if($download){
    $html=ob_get_clean();
    try{
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        $pdf=erRecordPdf($html);
        logSecurityEvent($conn,'ER Record Downloaded','Encounter '.$patient['patient_code']);
        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="ER-Record-'.preg_replace('/[^A-Za-z0-9-]/','',$patient['patient_code']).'.pdf"');header('Content-Length: '.strlen($pdf));header('Cache-Control: no-store, private');
        echo $pdf;
    }catch(Throwable $e){http_response_code(503);header('Content-Type: text/html; charset=utf-8');error_log('ER PDF failed; code '.(int)$e->getCode());echo '<p>PDF download is temporarily unavailable. Open the form and use Print / Save as PDF.</p><a href="er_record.php?id='.(int)$patient_id.'">Back to ER Record</a>';}
}
