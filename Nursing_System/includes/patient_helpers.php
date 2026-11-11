<?php
/**
 * Patient ID Generation Helper
 * Format: JVGH-YYYY-MM-DD-####
 * Where:
 * JVGH = Hospital code
 * YYYY-MM-DD = Current date
 * #### = Sequential counter (resets only when the date changes)
 */

const DEFAULT_PATIENT_STATUS = 'triage';
const PERMANENT_PATIENT_ID_SEQUENCE_DIGITS = 6;
const PATIENT_CODE_SEQUENCE_DIGITS = 4;
const PATIENT_QUEUE_SEQUENCE_DIGITS = 4;

function patientStatusWorkflow() {
    return [
        DEFAULT_PATIENT_STATUS => 'Triage',
        'waiting_for_consultation' => 'Waiting for Consultation',
        'consultation' => 'Consultation',
        'treatment' => 'Under Treatment',
        'waiting_results' => 'Waiting Results',
        'ready_for_disposition' => 'Ready for Disposition',
        'for_admission' => 'For Admission',
        'discharge' => 'Discharged',
        'admit_inpatient' => 'Admission',
        'trans_in' => 'Trans In',
        'trans_out' => 'Trans Out',
        'referred' => 'Referred',
        'ama' => 'AMA',
        'hama' => 'HAMA',
        'thoc' => 'THOC',
        'absconded' => 'Absconded',
        'dama' => 'DAMA',
        'doa' => 'DOA',
        'death' => 'Death',
        'er_death' => 'ER Death',
        'expired' => 'Expired'
    ];
}

function activePatientStatuses() {
    return [
        DEFAULT_PATIENT_STATUS,
        'waiting_for_consultation',
        'consultation',
        'treatment',
        'waiting_results',
        'ready_for_disposition'
    ];
}

function inactivePatientStatuses() {
    return [
        'discharge',
        'trans_in',
        'trans_out',
        'referred',
        'ama',
        'hama',
        'thoc',
        'absconded',
        'dama',
        'doa',
        'death',
        'er_death',
        'expired'
    ];
}

function bedOccupyingPatientStatuses() {
    return array_values(array_unique(array_merge(activePatientStatuses(), [
        'admit_inpatient'
    ])));
}

function patientStatusEnumValues() {
    return array_values(array_unique(array_merge(array_keys(patientStatusWorkflow()), [
        'arrival',
        'waiting_area',
        'initial_assessment',
        'diagnostics',
        'treatment_management',
        'reassessment',
        'disposition_decision',
        'refer_other_facility',
        'ama',
        'registration',
        'for_laboratory',
        'for_consult',
        'under_treatment',
        'for_admission',
        'for_discharge',
        'discharged',
        'admission',
        'trans_in',
        'trans_out',
        'absconded',
        'hama',
        'thoc',
        'death',
        'dama',
        'doa',
        'er_death'
    ])));
}

function triageLevelOptions() {
    return [
        'red' => 'Emergent',
        'yellow' => 'Urgent',
        'green' => 'Non-urgent',
        'black' => 'DOA / Expired'
    ];
}

function wardDepartmentOptions() {
    return [
        'MEDICAL' => 'MEDICAL',
        'PEDIA' => 'PEDIA',
        'OB' => 'OB',
        'NEWBORN' => 'NEWBORN',
        'NON-TRAUMA SURGERY' => 'NON-TRAUMA SURGERY',
        'TRAUMA SURGERY' => 'TRAUMA SURGERY'
    ];
}

function diagnosisOptions() {
    $diagnoses = <<<'DIAGNOSES'
ABDOMINAL BLUNT INJURY
ABDOMINAL COLIC
ABNORMAL UTERINE BLEEDING
ABORTION
ABRASION
ABSCESS
ACID PEPTIC DISEASE
ACID RELATED DSE
ACS STEMI
ACUTE ABDOMEN
ACUTE ANGLE GLAUCOMA
ACUTE CORONARY SYNDROME
ACUTE ESOPHAGITIC EXUDATES
ACUTE GLOMERULONEPHRITIS
ACUTE KIDNEY INJURY
ACUTE PANCREATITIS
ACUTE PANIC ATTACK
ACUTE PEPTIC DISEASE
ACUTE PYELONEPHRITIS
ACUTE RESPIRATORY DISTRESS
ACUTE RESPIRATORY FAILURE
ACUTE TONSILOPHARYNGITIS
AGE
AGN
ALCOHOL INTOXICATION
ALLERGIC DERMATITS
ALLERGIC REACTION
ALLERGIC RHINITIS
AMOEBIASIS
ANAL FISSURES
ANEMIA
ANEURYSM
ANGINA
ANIMAL BITE
ANTERIOR EPISTAXIS
ANXIETY DISORDER
AOM
APHTHOUS ULCER
APPENDICITIS
APUD
ARD
ARTHRITIS
ASCARIASIS
ASSAULT
AURI/URTI
AVI/SVI
AVULSED WOUND
BACTEREMIA
BACTERIAL CONJUCTIVITIS
BACTERIAL VAGINOSIS
BAIAE
BALANCE DISORDER
BALANITIS
BARTHOLIN'S CYST
BELL'S PALSY
BENIGN FEBRILE CONVULSION
BFC
BITTEN TONGUE WOUND
BKA
BLOOD DYSCRESIA
BOIL
BOWEL OBSTRUCTION
BPH
BPPV
BREAST MASS
BRONCHIAL ASTHMA
BRONCHITIS
BURN
CAD
CANCER
CANDIDIASIS
CAP
CARBUNCLE
CARDIAC ARREST
CARDIO PULMONARY ARREST
CARDIOMEGALY
CARDIOMYOPATHY
CELLULITIS
CENTRAL NERVOUS SYSTEM
CERVICAL CANCER
CHF
CHICKEN POX
CHOLECYSTITIS
CHOLELITHIASIS
CHRONIC HICCUP
CHRONIC LIMB ISCHEMIC
CKD
COLON MALIGNANCY
COMMON COLDS
COMPARTMENT SYNDROME
COMPLETE INTESTINAL OBSTRUCTION
CONCUSSION
CONSTIPATION
CONTUSION
COPD
COSTOCHONDRITIS
COVID 19 RESOLVING
COVID-19 CONFIRMED
CPA
CVA
CVD
CYSTITIS
DEAD ON ARRIVAL
DEEP VEIN THROMBOSIS
DENGUE FEVER SYNDROME
DENTAL PLAQUES
DERMATITIS
DIABETIC FOOT
DIARRHEA
DIFFICULTY OF BREATHING
DISLOCATED
DKA
DM
DRUG OVERDOSE
DRUG REACTION
DRY EYE SYNDROME
DVT
DYSLIPIDEMIA
DYSMENORRHEA
DYSPEPSIA
EARLY MENARCH
ECG NSR
ECZEMA
ELECTRIC CUSTION
ELECTROLYTE IMBALANCE
EMPHYSEMA
EPIDEDYMIS
EPILEPSY SYNDROME
ERROR OF REFRACTION
ERROR OF VISSION
ESSENTIALLY NORMAL
EXTERNAL HORDEOLUM
FALL
FAMILY PLANNING
FASCICULITIS
FATTY LIVER
FEBRILE ILLNESS
FEBRILE SEIZURE
FEBRILE STATUS EPILEPTICUS
FEVER OF UNKNOWN ORIGIN
FHTN
FIREWORK RELATED INJURY
FOLLICULITIS
FOOD INTOLERANCE
FOOD POISONING
FOR INQUEST
FOR WORK UP
FOREIGN BODY/ INGESTION
FRACTURE
FRICTION BLISTER
FUNCTIONAL ILEUS
FURUNCLE
G.I BLEEDING
GALLSTONE
GASTRITIS
GERD
GIARDIASIS
GINGIVITIS
GLOBAL DEVELOPMENTAL DELAYED
GOITER
GONORRHEA
GOUT
GRAVE'S DISEASE
GUM BLEEDING
GYNE PATHOLOGY
HAND FOOT AND MOUTH DISEASE
HASCVD
HCVD
HEAD INJURY
HEART DISEASE
HEART FAILURE
HEAT EXHAUSTION
HEAT STROKE
HEMATOMA
HEMORRHAGE
HEMORRHOIDS
HEP B
HEPATIC LIVER
HERNIA
HERNIATED DISC
HERPES ZOASTER
HFMD
HIV
HPD
HYDROCEPHALUS
HYDRONEPHROSIS
HYPERACIDITY
HYPERCHOLESTEROLEMIA
HYPEREMESIS GRAVIDARUM
HYPERSENSITIVITY REACTION
HYPERTENSION
HYPERTHERMIA
HYPERTHYROIDISM
HYPERURICEMIA
HYPERVENTILATION SYNDROME
HYPOGLYCEMIA
HYPOKALEMIA
HYPOPHOSPHATASIA
HYPOTHYROIDISM
HYPOVALEMIC SHOCK
IMMUNE DISORDER
IMPETIGO
INCREASE ICP
INFECTED UMBILICUS
INFECTED WOUND
INFECTION
INFLUENZA
INHALATION INJURY
INSECT BITE
INSOMIA
INTERTRIGO
INTOXICATION
INTRACRANIAL MASS
INTUSSUSCEPTION
IPF
JAUNDICE
KAWASAKI DISEASE
KHP
KIDNEY DISEASE
LACERATED WOUND
LACTOSE INTOLERANCE
LEPTOSPIROSIS
LESION
LEUKEMIA
LIVER CIRRHOSIS
LIVER PATHOLOGY
LIVER SCHISTOSOMIASIS
LOCK JAW
LYMPHADENITIS
LYMPHADENOPATHY
MASS (TISSUE)
MASTITIS
MAULING
MEASLES
MEDICAL CERTIFICATE
MENINGITIS
MENOPAUSE
MENSTRUATION
MENTAL DISORDER
MIGRAINE
MILD PHYSICAL INJURY
MILIRIA
MITTELSCHMERZ
MOUTH SORE
MSD
MSS
MUMPS
MUSCULOSKELETAL STRAIN
MYOCARDIAL INFARCTION
MYOMA
NASPARIGEAL
NCA
NECROTOMY FASCITIS
NEPHRITIS
NEPHROLITHIASIS
NEPHROTIC SYNDROME
NEUROCIRCULATORY ASTHENIA
NEUROGENIC BLADDER
NEUROPATHY
NEW BORN
NON HEALING WOUND
NON SPECIFIC VALVE VAGINITIS
NSVE
NUEROGENIC BLEEDING
NUMBNESS
OBSTRUCTION
ODONTALGIA
ORAL CANDIDIASIS
ORAL THRUSH
ORTHOSTATIC HYPOTENSION
OSTEOATHRITIS
OTITIS EXTERNA
OTITIS MEDIA
OUT WHEN CALLED
OVERDOSE
PAD
PAOD
PARKINSONS DISEASE
PARONYCHIA
PAROTITIS
PATELLAR TENDONITIS
PCAP
PCOS
PELVIC MASS
PELVIC ORGAN PROLAPSE
PERTUSSIS
PHEOCHROMOCYTOMA
PHYSICAL INJURY
PID
PKI
PLACENTA FREBIA
PLANTAR FIBROMA
PLEURAL EFFUSION
PNEUMONIA
POLLICULITIS
POLYPS
POST MENOPAUSAL BLEEDING
POST TEETH EXTRACTION BLEEDING
PRE EMPLOYMENT
PRE TERM LABOR
PREGNANCY
PREMATURE
PRENATAL CHECK UP
PRIMARY COMPLEX
PROSTATE CANCER
PROSTATOMEGALY
PSORIASIS
PTB
PUD
PULMONARY CONGESTION
PUNCTURED WOUND
PUSTULES
PVC
RASHES
RENAL CHOLIC
RENAL PATHOLOGY
RHD
RHEUMATIC FEVER
RHINITIS
ROSEOLA INFANTUM
S/P LAPROSCOPIC CYSTECTOMY
SCABIES
SCIATICA
SEIZURE DISORDER
SEPSIS
SEPTIC SHOCK
SEVERE DEHYDRATION
SHINGLES
SINUSITIS
SIPS
SLE
SOB
SOFT TISSUE INJURY
SPINE DEFORMITY
SPRAIN
STD
STI
STYE
SUBARACHNOID HEMORRHAGE
SUPRAORBITAL
SWELLING
SYMPTOMATIC BRADYCARDIA
SYNCOPE
TENSION HEADACHE
TESTICULAR TORSION
THYROID MALIGNANCY
THYROID PATHOLOGY
TINEA CORPIS
TMJ DYSFUCTION
TONSILITIS
TOOTH DECAY
TRACHEO ESOPHAGEAL FISTULA
TRANSIENT ISCHEMIC ATTACK
TRAUMA
TRAUMATIC BRAIN IJURY
TRAUMATIC BRAIN INJURY
TRAUMATIC CONJUNCTIVITIS
TRAUMATIC EYE INJURY
TRICHOMONIASIS
TBI
TYPOID FEVER
UGIB
ULCER
URETEROLITHIASIS
URINARY RETENTION
UROLITHIASIS
UROSEPSIS
URTICARIA
UTI
VAGINAL BLEEDING
VARICELLA ZOSTER INFECTION
VASOVAGAL REFLEXES
VEHICULAR ACCIDENT
VERTIGO
VIRAL EXANTHEM
VITAMIN B DEFICENCY
WELL BABY
HYPOXIC ISCHEMIC ENCELOPHALOPATHY
DIAGNOSES;

    $items = array_filter(array_map('trim', preg_split('/\R/', $diagnoses)));
    return array_values(array_unique($items));
}

function triageLevelEnumValues() {
    return array_values(array_unique(array_merge(array_keys(triageLevelOptions()), [
        'emergency',
        'urgent',
        'non-urgent'
    ])));
}

function normalizePatientCodeDate($date = null) {
    if (!$date) {
        return date('Y-m-d');
    }

    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        return date('Y-m-d');
    }

    return $date;
}

function generatePatientCode($conn, $date = null) {
    $codeDate = normalizePatientCodeDate($date);
    
    // Sequence resets for every Patient ID date.
    $prefix = "JVGH-" . $codeDate . "-";
    $suffixStart = strlen($prefix) + 1;
    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING(patient_code, ?) AS UNSIGNED)) as last_sequence
        FROM patients
        WHERE patient_code LIKE CONCAT(?, '%')
    ");
    $stmt->bind_param("is", $suffixStart, $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lastSequence = $result['last_sequence'];
    $nextSequence = $lastSequence === null ? 0 : ((int) $lastSequence + 1);
    
    // Format: JVGH-YYYY-MM-DD-####, starting at 0000 for each new date.
    $patientCode = $prefix . str_pad($nextSequence, PATIENT_CODE_SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
    
    return $patientCode;
}

function generatePermanentPatientId($conn) {
    $prefix = 'PT-';
    $suffixStart = strlen($prefix) + 1;
    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING(permanent_patient_id, ?) AS UNSIGNED)) as last_sequence
        FROM patients
        WHERE permanent_patient_id LIKE CONCAT(?, '%')
    ");
    $stmt->bind_param("is", $suffixStart, $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lastSequence = $result['last_sequence'];
    $nextSequence = $lastSequence === null ? 1 : ((int) $lastSequence + 1);

    return $prefix . str_pad($nextSequence, PERMANENT_PATIENT_ID_SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
}

function generatePatientQueueNumber($conn, $date = null) {
    $queueDate = normalizePatientCodeDate($date);
    $stmt = $conn->prepare("SELECT MAX(queue_number) as last_queue FROM patients WHERE queue_date = ?");
    $stmt->bind_param("s", $queueDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lastQueue = $result['last_queue'];

    return $lastQueue === null ? 1 : ((int) $lastQueue + 1);
}

function formatQueueNumber($queueNumber) {
    if (!$queueNumber) {
        return 'N/A';
    }

    return str_pad((int) $queueNumber, PATIENT_QUEUE_SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
}

function calculateAge($dateOfBirth) {
    if (!isValidPatientDate($dateOfBirth)) {
        return null;
    }

    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age;
}

function isValidPatientDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return false;
    }

    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    return $parsedDate && $parsedDate->format('Y-m-d') === $date;
}

function normalizePatientDateInput($date) {
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }

    $formats = ['m-d-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $parsedDate = DateTime::createFromFormat($format, $date);
        if ($parsedDate && $parsedDate->format($format) === $date) {
            return $parsedDate->format('Y-m-d');
        }
    }

    return '';
}

function formatPatientDate($date) {
    if (!isValidPatientDate($date)) {
        return 'N/A';
    }

    return date('m-d-Y', strtotime($date));
}

function patientFullName($patient) {
    $parts = [
        $patient['last_name'] ?? '',
        $patient['first_name'] ?? '',
        $patient['middle_name'] ?? ''
    ];

    return trim(implode(', ', array_filter($parts)));
}

function patientStatusOptions() {
    return patientStatusWorkflow();
}

function patientStatusWorkflowOrder() {
    return [
        DEFAULT_PATIENT_STATUS,
        'waiting_for_consultation',
        'consultation',
        'treatment',
        'waiting_results',
        'ready_for_disposition',
        'for_admission',
        'admit_inpatient',
        'discharge',
        'trans_in',
        'trans_out',
        'referred',
        'ama',
        'hama',
        'thoc',
        'absconded',
        'dama',
        'doa',
        'death',
        'er_death',
        'expired'
    ];
}

function patientStatusRank($status) {
    $ranks = array_flip(patientStatusWorkflowOrder());
    return $ranks[$status] ?? null;
}

function forwardPatientStatusOptions($options, $currentStatus) {
    $currentRank = patientStatusRank($currentStatus);
    if ($currentRank === null) {
        return $options;
    }

    $forwardOptions = array_filter($options, function ($label, $status) use ($currentRank) {
        $rank = patientStatusRank($status);
        return $rank === null || $rank >= $currentRank;
    }, ARRAY_FILTER_USE_BOTH);

    if (empty($forwardOptions) && isset(patientStatusOptions()[$currentStatus])) {
        return [$currentStatus => strtoupper(statusLabel($currentStatus))];
    }

    return $forwardOptions;
}

function patientWorkflowStatusOptions() {
    return [
        DEFAULT_PATIENT_STATUS => 'TRIAGE',
        'waiting_for_consultation' => 'WAITING FOR CONSULTATION',
        'consultation' => 'CONSULTATION',
        'treatment' => 'UNDER TREATMENT',
        'waiting_results' => 'WAITING RESULTS',
        'ready_for_disposition' => 'READY FOR DISPOSITION'
    ];
}

function nurseWorkflowStatusOptions() {
    return [
        DEFAULT_PATIENT_STATUS => 'TRIAGE',
        'waiting_for_consultation' => 'WAITING FOR CONSULTATION'
    ];
}

function patientDispositionOptions() {
    return [
        'discharge' => 'DISCHARGED',
        'for_admission' => 'FOR ADMISSION',
        'referred' => 'REFERRED',
        'ama' => 'AMA',
        'thoc' => 'THOC',
        'absconded' => 'ABSCONDED',
        'dama' => 'DAMA',
        'doa' => 'DOA',
        'er_death' => 'ER DEATH'
    ];
}

function wardDispositionOptions() {
    return [
        'discharge' => 'DISCHARGED',
        'trans_in' => 'TRANS IN',
        'trans_out' => 'TRANS OUT',
        'absconded' => 'ABSCONDED',
        'hama' => 'HAMA',
        'thoc' => 'THOC',
        'death' => 'DEATH'
    ];
}

function emergencyRoomBedDefinitions() {
    $beds = [];
    for ($room = 1; $room <= 8; $room++) {
        $roomNumber = 'ER-' . str_pad((string) $room, 2, '0', STR_PAD_LEFT);
        for ($bed = 1; $bed <= 2; $bed++) {
            $beds[] = [
                'bed_number' => $roomNumber . '-B' . $bed,
                'room_number' => $roomNumber,
                'bed_type' => 'Emergency'
            ];
        }
    }

    return $beds;
}

function ensureEmergencyRoomBeds($conn) {
    foreach (emergencyRoomBedDefinitions() as $bed) {
        $stmt = $conn->prepare("
            INSERT INTO beds (bed_number, room_number, bed_type, status)
            VALUES (?, ?, ?, 'available')
            ON DUPLICATE KEY UPDATE room_number = VALUES(room_number), bed_type = VALUES(bed_type)
        ");
        $stmt->bind_param("sss", $bed['bed_number'], $bed['room_number'], $bed['bed_type']);
        if (!$stmt->execute()) {
            return 'Failed to sync emergency room beds: ' . $stmt->error;
        }
    }

    return true;
}

function obPediaWardRoomNumbers(): array {
    return [
        '201','201','201','201','201','201','201','201',
        '202','202','202','202',
        '203','203','203',
        '204','204','204',
        '205','205',
        '207','207','207','207',
        '201 PEDIA (C)',
        '202 PEDIA (D)',
        '204 PEDIA (D)',
        '206 A',
        '207 OB','207 OB','207 OB','207 OB','207 OB',
        '208 OB (NSD)','208 OB (NSD)','208 OB (NSD)','208 OB (NSD)','208 OB (NSD)','208 OB (NSD)',
        '209 A',
        '211 ISO A',
        'HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND','HW 2ND',
        'NB','NB','NB','NB','NB','NB','NB','NB','NB','NB','NB','NB',
    ];
}

function wardBedDefinitions() {
    $beds = [];

    $roomBedCounts = [];
    foreach (obPediaWardRoomNumbers() as $roomNumber) {
        $roomBedCounts[$roomNumber] = ($roomBedCounts[$roomNumber] ?? 0) + 1;
        $beds[] = [
            'bed_number' => $roomNumber . '-B' . $roomBedCounts[$roomNumber],
            'room_number' => $roomNumber,
            'bed_type' => 'General'
        ];
    }

    foreach ([3 => 35, 4 => 34] as $room => $bedCount) {
        $roomNumber = 'WARD-' . str_pad((string) $room, 2, '0', STR_PAD_LEFT);
        for ($bed = 1; $bed <= $bedCount; $bed++) {
            $beds[] = [
                'bed_number' => $roomNumber . '-B' . $bed,
                'room_number' => $roomNumber,
                'bed_type' => 'General'
            ];
        }
    }

    return $beds;
}

function ensureWardBeds($conn) {
    foreach (wardBedDefinitions() as $bed) {
        $stmt = $conn->prepare("
            INSERT INTO beds (bed_number, room_number, bed_type, status)
            VALUES (?, ?, ?, 'available')
            ON DUPLICATE KEY UPDATE room_number = VALUES(room_number), bed_type = VALUES(bed_type)
        ");
        $stmt->bind_param("sss", $bed['bed_number'], $bed['room_number'], $bed['bed_type']);
        if (!$stmt->execute()) {
            return 'Failed to sync ward beds: ' . $stmt->error;
        }
    }

    return true;
}

function statusLabel($status) {
    $options = patientStatusOptions();
    return $options[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function triageLevelLabel($level) {
    if (!$level) {
        return 'N/A';
    }

    $options = triageLevelOptions();
    return $options[$level] ?? ucwords(str_replace('-', ' ', $level));
}

function formatWaitTime($minutes) {
    $minutes = max(0, (int) $minutes);
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0) {
        return $hours . ' hr ' . $remainingMinutes . ' min';
    }

    return $remainingMinutes . ' min';
}

function formatDaysHoursMinutes($minutes) {
    $minutes = max(0, (int) $minutes);
    $days = intdiv($minutes, 1440);
    $hours = intdiv($minutes % 1440, 60);
    $remainingMinutes = $minutes % 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . ' hr';
    }
    $parts[] = $remainingMinutes . ' min';

    return implode(' ', $parts);
}

function patientCurrentWaitMinutesSql($patientAlias = 'p') {
    $patientId = $patientAlias . '.patient_id';
    $triageDate = $patientAlias . '.triage_date';
    $triageTime = $patientAlias . '.triage_time';
    $arrivalTime = $patientAlias . '.arrival_time';

    return "GREATEST(TIMESTAMPDIFF(MINUTE, COALESCE((
        SELECT COALESCE(pt.status_ended_at, pt.created_at)
        FROM patient_tracking pt
        WHERE pt.patient_id = $patientId
            AND pt.action = 'Status Updated'
        ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
        LIMIT 1
    ), TIMESTAMP($triageDate, $triageTime), $arrivalTime), NOW()), 0)";
}

function ensureStatusTrackingSchema($conn) {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM patient_tracking");
    if (!$result) {
        return 'Patient tracking table is missing';
    }

    while ($column = $result->fetch_assoc()) {
        $columns[$column['Field']] = true;
    }

    $requiredColumns = [
        'from_status' => "ALTER TABLE patient_tracking ADD COLUMN from_status VARCHAR(50) NULL AFTER description",
        'to_status' => "ALTER TABLE patient_tracking ADD COLUMN to_status VARCHAR(50) NULL AFTER from_status",
        'status_started_at' => "ALTER TABLE patient_tracking ADD COLUMN status_started_at DATETIME NULL AFTER to_status",
        'status_ended_at' => "ALTER TABLE patient_tracking ADD COLUMN status_ended_at DATETIME NULL AFTER status_started_at"
    ];

    foreach ($requiredColumns as $column => $alterSql) {
        if (!isset($columns[$column]) && !$conn->query($alterSql)) {
            return 'Failed to update patient tracking schema: ' . $conn->error;
        }
    }

    return true;
}

function ensurePatientWorkflowSchema($conn) {
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM patients");
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['ward_department'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN ward_department VARCHAR(30) NOT NULL DEFAULT 'MEDICAL' AFTER current_bed")) {
            return 'Failed to add patient ward department: ' . $conn->error;
        }
    }

    if (!isset($columns['permanent_patient_id'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN permanent_patient_id VARCHAR(30) NULL AFTER patient_id")) {
            return 'Failed to add permanent patient ID: ' . $conn->error;
        }
    }

    if (!isset($columns['final_diagnosis'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN final_diagnosis VARCHAR(150) NULL AFTER medical_history")) {
            return 'Failed to add patient final diagnosis: ' . $conn->error;
        }
    }

    if (!isset($columns['specific_diagnosis'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN specific_diagnosis TEXT NULL AFTER final_diagnosis")) {
            return 'Failed to add patient specific diagnosis: ' . $conn->error;
        }
    }

    if (!isset($columns['queue_date'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN queue_date DATE NULL AFTER patient_code")) {
            return 'Failed to add patient queue date: ' . $conn->error;
        }
    }

    if (!isset($columns['queue_number'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN queue_number INT NULL AFTER queue_date")) {
            return 'Failed to add patient queue number: ' . $conn->error;
        }
    }

    if (!isset($columns['civil_status'])) {
        if (!$conn->query("ALTER TABLE patients ADD COLUMN civil_status ENUM('Single','Married','Widowed','Separated','Divorced') NULL AFTER gender")) {
            return 'Failed to add patient civil status: ' . $conn->error;
        }
    }

    if (!$conn->query("UPDATE patients SET queue_date = DATE(created_at) WHERE queue_date IS NULL")) {
        return 'Failed to backfill patient queue dates: ' . $conn->error;
    }

    // Never assign or rewrite Hospital Numbers during schema checks.
    // Missing identities require reviewed reconciliation, not page-load backfills.

    $triageCivilStatusResult = $conn->query("SHOW COLUMNS FROM triage WHERE Field = 'civil_status'");
    if ($triageCivilStatusResult && $triageCivilStatusResult->num_rows > 0) {
        if (!$conn->query("
            UPDATE patients p
            INNER JOIN triage t ON t.patient_id = p.patient_id
            SET p.civil_status = t.civil_status
            WHERE p.civil_status IS NULL
                AND t.civil_status IS NOT NULL
                AND t.civil_status <> ''
        ")) {
            return 'Failed to move civil status to patient records: ' . $conn->error;
        }
    }

    // Name and date of birth are lookup hints, never proof of identity.

    $indexResult = $conn->query("SHOW INDEX FROM patients WHERE Key_name = 'idx_permanent_patient_id'");
    if ($indexResult && $indexResult->num_rows === 0) {
        if (!$conn->query("CREATE INDEX idx_permanent_patient_id ON patients(permanent_patient_id)")) {
            return 'Failed to index permanent patient IDs: ' . $conn->error;
        }
    }

    // Patient workflows must never alter employee account roles. Role migrations
    // belong to the explicit account-schema deployment process.

    if (!$conn->query("
        UPDATE patients p
        INNER JOIN (
            SELECT
                p1.patient_id,
                COUNT(p2.patient_id) as queue_number
            FROM patients p1
            INNER JOIN patients p2
                ON p2.queue_date = p1.queue_date
                AND p2.patient_id <= p1.patient_id
            WHERE p1.queue_number IS NULL
            GROUP BY p1.patient_id
        ) q ON q.patient_id = p.patient_id
        SET p.queue_number = q.queue_number
        WHERE p.queue_number IS NULL
    ")) {
        return 'Failed to backfill patient queue numbers: ' . $conn->error;
    }

    $statusValues = array_map(function ($status) use ($conn) {
        return "'" . $conn->real_escape_string($status) . "'";
    }, patientStatusEnumValues());
    $statusSql = "ALTER TABLE patients MODIFY status ENUM(" . implode(',', $statusValues) . ") NOT NULL DEFAULT '" . DEFAULT_PATIENT_STATUS . "'";
    if (!$conn->query($statusSql)) {
        return 'Failed to update patient status workflow: ' . $conn->error;
    }

    $triageValues = array_map(function ($level) use ($conn) {
        return "'" . $conn->real_escape_string($level) . "'";
    }, triageLevelEnumValues());
    $triageSql = "ALTER TABLE triage MODIFY triage_level ENUM(" . implode(',', $triageValues) . ") NOT NULL";
    if (!$conn->query($triageSql)) {
        return 'Failed to update triage priority workflow: ' . $conn->error;
    }

    $statusMappings = [
        'arrival' => DEFAULT_PATIENT_STATUS,
        'waiting_area' => 'waiting_for_consultation',
        'initial_assessment' => 'waiting_for_consultation',
        'diagnostics' => 'waiting_results',
        'treatment_management' => 'treatment',
        'reassessment' => 'ready_for_disposition',
        'disposition_decision' => 'ready_for_disposition',
        'for_laboratory' => 'waiting_results',
        'for_consult' => 'waiting_for_consultation',
        'registration' => DEFAULT_PATIENT_STATUS,
        'under_treatment' => 'treatment',
        'for_admission' => 'for_admission',
        'for_discharge' => 'ready_for_disposition',
        'discharged' => 'discharge',
        'admission' => 'for_admission',
        'expired' => 'er_death',
        'refer_other_facility' => 'thoc',
        'ama' => 'dama'
    ];
    foreach ($statusMappings as $oldStatus => $newStatus) {
        $stmt = $conn->prepare("UPDATE patients SET status = ? WHERE status = ?");
        $stmt->bind_param("ss", $newStatus, $oldStatus);
        $stmt->execute();
    }

    $triageMappings = [
        'emergency' => 'red',
        'urgent' => 'yellow',
        'non-urgent' => 'green'
    ];
    foreach ($triageMappings as $oldLevel => $newLevel) {
        $stmt = $conn->prepare("UPDATE triage SET triage_level = ? WHERE triage_level = ?");
        $stmt->bind_param("ss", $newLevel, $oldLevel);
        $stmt->execute();
    }

    return true;
}

function ensureErRecordTriageSchema($conn) {
    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM triage");
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
    } else {
        return 'Failed to inspect triage ER record schema: ' . $conn->error;
    }

    $alterStatements = [
        'medicolegal' => "ALTER TABLE triage ADD COLUMN medicolegal TINYINT(1) NOT NULL DEFAULT 0 AFTER assessment",
        'ip' => "ALTER TABLE triage ADD COLUMN ip TINYINT(1) NOT NULL DEFAULT 0 AFTER medicolegal",
        'referred_by' => "ALTER TABLE triage ADD COLUMN referred_by VARCHAR(150) NULL AFTER ip",
        'coordinated' => "ALTER TABLE triage ADD COLUMN coordinated TINYINT(1) NOT NULL DEFAULT 0 AFTER referred_by",
        'vital_pr' => "ALTER TABLE triage ADD COLUMN vital_pr VARCHAR(20) NULL AFTER vital_signs",
        'vital_height' => "ALTER TABLE triage ADD COLUMN vital_height VARCHAR(20) NULL AFTER vital_pr",
        'vital_weight' => "ALTER TABLE triage ADD COLUMN vital_weight VARCHAR(20) NULL AFTER vital_height",
        'vital_spo2' => "ALTER TABLE triage ADD COLUMN vital_spo2 VARCHAR(20) NULL AFTER vital_weight",
        'vital_cbg' => "ALTER TABLE triage ADD COLUMN vital_cbg VARCHAR(20) NULL AFTER vital_spo2"
    ];

    foreach ($alterStatements as $column => $sql) {
        if (!isset($columns[$column]) && !$conn->query($sql)) {
            return 'Failed to add triage ER record field ' . $column . ': ' . $conn->error;
        }
    }

    return true;
}

function updatePatientStatus($conn, $patientId, $status, $userId, $finalDiagnosis = null, $specificDiagnosis = null) {
    $patientId = (int) $patientId;
    $options = patientStatusOptions();
    $finalDiagnosis = trim((string) $finalDiagnosis);
    $specificDiagnosis = trim((string) $specificDiagnosis);

    if (!$patientId || !isset($options[$status])) {
        return 'Invalid patient status';
    }

    $statusesRequiringDiagnosis = ['discharge', 'for_admission', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'er_death', 'expired'];
    if (in_array($status, $statusesRequiringDiagnosis, true) && $finalDiagnosis === '') {
        return 'Please choose the diagnosis before updating the patient status.';
    }

    // Never execute runtime DDL inside a caller's clinical transaction.
    $callerTransaction=(bool)$conn->query('SELECT @@in_transaction AS active')->fetch_assoc()['active'];
    foreach ($callerTransaction?[]:[ensureStatusTrackingSchema($conn), ensurePatientWorkflowSchema($conn)] as $schemaResult) {
        if ($schemaResult !== true) {
            return $schemaResult;
        }
    }

    $patientCheck = $conn->prepare("
        SELECT
            patient_id,
            status,
            COALESCE(TIMESTAMP(triage_date, triage_time), arrival_time) as status_started_at
        FROM patients
        WHERE patient_id = ?
    ");
    $patientCheck->bind_param("i", $patientId);
    $patientCheck->execute();
    $patient = $patientCheck->get_result()->fetch_assoc();
    if (!$patient) {
        return 'Patient not found';
    }

    if ($patient['status'] === $status) {
        return true;
    }

    if ($patient['status'] === 'for_admission' && in_array($status, ['discharge', 'discharged'], true)) {
        return 'Patients marked for admission cannot be discharged. Admit or assign the patient first.';
    }

    if (in_array($patient['status'], inactivePatientStatuses(), true)) {
        return 'Inactive patient status cannot be changed.';
    }

    $currentStatusRank = patientStatusRank($patient['status']);
    $nextStatusRank = patientStatusRank($status);
    if ($currentStatusRank !== null && $nextStatusRank !== null && $nextStatusRank < $currentStatusRank) {
        return 'Patient status cannot go back to a previous status.';
    }

    $lastTracking = $conn->prepare("SELECT COALESCE(status_ended_at, created_at) as ended_at FROM patient_tracking WHERE patient_id = ? AND action = 'Status Updated' ORDER BY created_at DESC LIMIT 1");
    $lastTracking->bind_param("i", $patientId);
    $lastTracking->execute();
    $lastTrackingRow = $lastTracking->get_result()->fetch_assoc();
    $statusStartedAt = $lastTrackingRow['ended_at'] ?? $patient['status_started_at'];

    $durationStmt = $conn->prepare("SELECT NOW() as ended_at, GREATEST(TIMESTAMPDIFF(MINUTE, ?, NOW()), 0) as duration_minutes");
    $durationStmt->bind_param("s", $statusStartedAt);
    $durationStmt->execute();
    $durationRow = $durationStmt->get_result()->fetch_assoc();
    $statusEndedAt = $durationRow['ended_at'];
    $durationMinutes = (int) ($durationRow['duration_minutes'] ?? 0);

    // Clinical update and its histories must either all commit or all roll back.
    $outer=(bool)$conn->query('SELECT @@in_transaction AS active')->fetch_assoc()['active'];
    if($outer)$conn->query('SAVEPOINT er_status_update');else $conn->begin_transaction();
    try {
    $locked=$conn->prepare('SELECT status FROM patients WHERE patient_id=? FOR UPDATE');
    $locked->bind_param('i',$patientId);$locked->execute();$lockedPatient=$locked->get_result()->fetch_assoc();
    if(!$lockedPatient||$lockedPatient['status']!==$patient['status'])throw new RuntimeException('Patient status changed. Refresh and retry.');

    if (in_array($status, $statusesRequiringDiagnosis, true)) {
        $stmt = $conn->prepare("UPDATE patients SET status = ?, final_diagnosis = ?, specific_diagnosis = ? WHERE patient_id = ?");
        $stmt->bind_param("sssi", $status, $finalDiagnosis, $specificDiagnosis, $patientId);
    } else {
        $stmt = $conn->prepare("UPDATE patients SET status = ? WHERE patient_id = ?");
        $stmt->bind_param("si", $status, $patientId);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update patient status');
    }

    if (in_array($status, inactivePatientStatuses())) {
        $bedStmt = $conn->prepare("UPDATE beds SET status = 'available', patient_id = NULL WHERE patient_id = ?");
        $bedStmt->bind_param("i", $patientId);
        $bedStmt->execute();

        $patientBedStmt = $conn->prepare("UPDATE patients SET current_bed = NULL WHERE patient_id = ?");
        $patientBedStmt->bind_param("i", $patientId);
        $patientBedStmt->execute();
    }

    $trackStmt = $conn->prepare("
        INSERT INTO patient_tracking (
            patient_id,
            action,
            description,
            from_status,
            to_status,
            status_started_at,
            status_ended_at,
            user_id,
            wait_time_minutes
        ) VALUES (?, 'Status Updated', ?, ?, ?, ?, ?, ?, ?)
    ");
    $description = 'Patient status changed from ' . statusLabel($patient['status']) . ' to ' . statusLabel($status) . ' after ' . formatWaitTime($durationMinutes);
    $trackStmt->bind_param("isssssii", $patientId, $description, $patient['status'], $status, $statusStartedAt, $statusEndedAt, $userId, $durationMinutes);

    if (!$trackStmt->execute()) {
        throw new RuntimeException('Patient status history could not be saved');
    }

    if (function_exists('logSecurityEvent')) {
        logSecurityEvent($conn, 'Patient Status Updated', $description, $userId);
    }

    require_once __DIR__.'/er-live-tracker.php';
    erTrackerObserve($conn,$patientId,(int)$userId,'clinical_status');
    if($outer)$conn->query('RELEASE SAVEPOINT er_status_update');else $conn->commit();
    return true;
    } catch(Throwable $e) {
        if($outer)$conn->query('ROLLBACK TO SAVEPOINT er_status_update');else $conn->rollback();
        error_log('Clinical status transaction failed; code '.(int)$e->getCode());
        return 'Patient status could not be saved. Refresh and retry.';
    }
}

function secureUploadedFile($file, $destinationDir, $allowedMimeTypes = null, $maxBytes = 5242880) {
    $allowedMimeTypes = $allowedMimeTypes ?? [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'text/plain' => 'txt'
    ];

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid upload payload.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File size is not allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimeTypes[$mimeType])) {
        return ['success' => false, 'error' => 'File type is not allowed.'];
    }

    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }

    $extension = $allowedMimeTypes[$mimeType];
    $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Unable to store uploaded file.'];
    }

    return [
        'success' => true,
        'path' => $targetPath,
        'stored_name' => $safeName,
        'original_name' => basename((string) $file['name']),
        'mime_type' => $mimeType,
        'size' => (int) $file['size']
    ];
}

function ensureAdmittedPatientSchema($conn) {
    $recordsSql = "
        CREATE TABLE IF NOT EXISTS admitted_patient_records (
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
        )
    ";

    if (!$conn->query($recordsSql)) {
        return 'Failed to create admission records table: ' . $conn->error;
    }

    $documentsSql = "
        CREATE TABLE IF NOT EXISTS admitted_patient_documents (
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
        )
    ";

    if (!$conn->query($documentsSql)) {
        return 'Failed to create admission documents table: ' . $conn->error;
    }

    return true;
}
