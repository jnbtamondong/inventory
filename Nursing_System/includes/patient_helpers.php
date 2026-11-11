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
        'referred' => 'Referred',
        'ama' => 'AMA',
        'thoc' => 'THOC',
        'absconded' => 'Absconded',
        'dama' => 'DAMA',
        'doa' => 'DOA',
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
        'referred',
        'ama',
        'thoc',
        'absconded',
        'dama',
        'doa',
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
        'red' => 'Immediate',
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

    if (!$conn->query("UPDATE patients SET queue_date = DATE(created_at) WHERE queue_date IS NULL")) {
        return 'Failed to backfill patient queue dates: ' . $conn->error;
    }

    if (!$conn->query("UPDATE patients SET permanent_patient_id = CONCAT('PT-', LPAD(patient_id, " . PERMANENT_PATIENT_ID_SEQUENCE_DIGITS . ", '0')) WHERE permanent_patient_id IS NULL OR permanent_patient_id = ''")) {
        return 'Failed to backfill permanent patient IDs: ' . $conn->error;
    }

    if (!$conn->query("UPDATE patients SET permanent_patient_id = CONCAT('PT-', SUBSTRING(permanent_patient_id, 5)) WHERE permanent_patient_id LIKE 'PID-%'")) {
        return 'Failed to update permanent patient ID prefix: ' . $conn->error;
    }

    if (!$conn->query("
        UPDATE patients p
        INNER JOIN (
            SELECT
                LOWER(TRIM(first_name)) as first_name_key,
                LOWER(TRIM(last_name)) as last_name_key,
                date_of_birth,
                MIN(permanent_patient_id) as permanent_patient_id
            FROM patients
            WHERE permanent_patient_id IS NOT NULL
                AND permanent_patient_id <> ''
            GROUP BY LOWER(TRIM(first_name)), LOWER(TRIM(last_name)), date_of_birth
        ) matched_patient
            ON matched_patient.first_name_key = LOWER(TRIM(p.first_name))
            AND matched_patient.last_name_key = LOWER(TRIM(p.last_name))
            AND matched_patient.date_of_birth = p.date_of_birth
        SET p.permanent_patient_id = matched_patient.permanent_patient_id
    ")) {
        return 'Failed to connect existing patient encounter IDs: ' . $conn->error;
    }

    $indexResult = $conn->query("SHOW INDEX FROM patients WHERE Key_name = 'idx_permanent_patient_id'");
    if ($indexResult && $indexResult->num_rows === 0) {
        if (!$conn->query("CREATE INDEX idx_permanent_patient_id ON patients(permanent_patient_id)")) {
            return 'Failed to index permanent patient IDs: ' . $conn->error;
        }
    }

    $userColumns = [];
    $userColumnsResult = $conn->query("SHOW COLUMNS FROM users");
    if ($userColumnsResult) {
        while ($column = $userColumnsResult->fetch_assoc()) {
            $userColumns[$column['Field']] = true;
        }
    }

    if (isset($userColumns['role'])) {
        $roleValues = array_map(function ($role) use ($conn) {
            return "'" . $conn->real_escape_string($role) . "'";
        }, array_keys(roleOptions()));
        if (!$conn->query("ALTER TABLE users MODIFY role ENUM(" . implode(',', $roleValues) . ") NOT NULL")) {
            return 'Failed to update user roles: ' . $conn->error;
        }
    }

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
        'trans_in' => 'admit_inpatient',
        'trans_out' => 'thoc',
        'hama' => 'dama',
        'death' => 'er_death',
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

function updatePatientStatus($conn, $patientId, $status, $userId, $finalDiagnosis = null, $specificDiagnosis = null) {
    $patientId = (int) $patientId;
    $options = patientStatusOptions();
    $finalDiagnosis = trim((string) $finalDiagnosis);
    $specificDiagnosis = trim((string) $specificDiagnosis);

    if (!$patientId || !isset($options[$status])) {
        return 'Invalid patient status';
    }

    $statusesRequiringDiagnosis = ['discharge', 'for_admission', 'referred', 'ama', 'dama', 'doa', 'er_death', 'expired'];
    if (in_array($status, $statusesRequiringDiagnosis, true) && $finalDiagnosis === '') {
        return 'Please choose the diagnosis before updating the patient status.';
    }

    foreach ([ensureStatusTrackingSchema($conn), ensurePatientWorkflowSchema($conn)] as $schemaResult) {
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

    if (in_array($status, $statusesRequiringDiagnosis, true)) {
        $stmt = $conn->prepare("UPDATE patients SET status = ?, final_diagnosis = ?, specific_diagnosis = ? WHERE patient_id = ?");
        $stmt->bind_param("sssi", $status, $finalDiagnosis, $specificDiagnosis, $patientId);
    } else {
        $stmt = $conn->prepare("UPDATE patients SET status = ? WHERE patient_id = ?");
        $stmt->bind_param("si", $status, $patientId);
    }

    if (!$stmt->execute()) {
        return 'Failed to update patient status';
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
        return 'Patient status updated, but tracking log failed';
    }

    return true;
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
