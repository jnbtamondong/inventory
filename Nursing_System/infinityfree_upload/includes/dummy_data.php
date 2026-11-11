<?php
// Generate dummy patient data
function generateDummyPatients($conn, $count = 100) {
    require_once __DIR__ . '/patient_helpers.php';

    $first_names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'James', 'Jessica', 'Robert', 'Amanda', 'William', 'Lisa', 'Richard', 'Karen', 'Joseph', 'Nancy', 'Thomas', 'Betty', 'Christopher', 'Margaret'];
    $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
    $middle_names = ['Reyes', 'Santos', 'Cruz', 'Dela Rosa', 'Aquino', 'Ramos', 'Mendoza', 'Flores'];
    $religions = ['Roman Catholic', 'Iglesia ni Cristo', 'Christian', 'Islam', 'None'];
    
    $complaints = [
        'Chest Pain', 'Shortness of Breath', 'Abdominal Pain', 'Headache', 'Fever', 
        'Dizziness', 'Back Pain', 'Nausea', 'Cough', 'Sore Throat', 'Fracture', 'Laceration',
        'Diabetes Management', 'Hypertension Check', 'Asthma Attack', 'Migraine'
    ];
    
    $triage_levels = ['emergency', 'urgent', 'non-urgent'];
    $statuses = ['triage', 'for_laboratory', 'for_consult', 'under_treatment', 'for_admission', 'admission'];
    
    // Get doctor and nurse IDs
    $doctors = $conn->query("SELECT user_id FROM users WHERE role = 'doctor'")->fetch_all(MYSQLI_ASSOC);
    $nurses = $conn->query("SELECT user_id FROM users WHERE role IN ('nurse', 'chief_nurse', 'nurse_supervisor', 'head_nurse', 'staff_nurse')")->fetch_all(MYSQLI_ASSOC);
    $doctor_ids = array_map(function($d) { return $d['user_id']; }, $doctors);
    $nurse_ids = array_map(function($n) { return $n['user_id']; }, $nurses);
    
    if (empty($doctor_ids) || empty($nurse_ids)) {
        return "No doctors or nurses found in database";
    }
    
    for ($i = 0; $i < $count; $i++) {
        $first_name = $first_names[array_rand($first_names)];
        $last_name = $last_names[array_rand($last_names)];
        $middle_name = $middle_names[array_rand($middle_names)];
        
        // Random age between 18-85
        $age = rand(18, 85);
        $date_of_birth = date('Y-m-d', strtotime("-$age years"));
        
        $gender = ['Male', 'Female', 'Other'][array_rand(['Male', 'Female', 'Other'])];
        $phone = '555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $religion = $religions[array_rand($religions)];
        $house_no_street = rand(100, 9999) . ' Main Street';
        $permanent_patient_id = generatePermanentPatientId($conn);
        $patient_code = generatePatientCode($conn);
        
        // Random triage/arrival time from today, so wait time stays current and readable.
        $minutes_since_midnight = max(0, floor((time() - strtotime('today')) / 60));
        $wait_minutes = rand(0, $minutes_since_midnight);
        $arrival_time = date('Y-m-d H:i:s', strtotime('-' . $wait_minutes . ' minutes'));
        
        $status = $statuses[array_rand($statuses)];
        $triage_level = $triage_levels[array_rand($triage_levels)];
        
        // Random assignment
        $doctor_id = $doctor_ids[array_rand($doctor_ids)];
        $nurse_id = null;
        $current_bed = null;
        
        // Insert patient
        $stmt = $conn->prepare("INSERT INTO patients (permanent_patient_id, patient_code, first_name, last_name, middle_name, date_of_birth, age, gender, religion, phone, house_no_street, admission_date, triage_date, triage_time, arrival_time, status, current_doctor, assigned_nurse, current_bed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE(?), TIME(?), ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssissssssssiii", $permanent_patient_id, $patient_code, $first_name, $last_name, $middle_name, $date_of_birth, $age, $gender, $religion, $phone, $house_no_street, $arrival_time, $arrival_time, $arrival_time, $status, $doctor_id, $nurse_id, $current_bed);
        
        if (!$stmt->execute()) {
            return "Error inserting patient: " . $stmt->error;
        }
        
        $patient_id = $conn->insert_id;
        
        // Insert triage for patient
        $chief_complaint = $complaints[array_rand($complaints)];
        $assessment = "Patient assessed and categorized as " . $triage_level;
        
        $vital_signs = json_encode([
            'temperature' => number_format(36.5 + (rand(-10, 10) / 10), 1),
            'blood_pressure' => rand(100, 180) . '/' . rand(60, 120),
            'heart_rate' => rand(60, 120),
            'respiratory_rate' => rand(12, 20)
        ]);
        
        $assigned_by = $doctor_id;
        $stmt = $conn->prepare("INSERT INTO triage (patient_id, triage_level, chief_complaint, assessment, vital_signs, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $patient_id, $triage_level, $chief_complaint, $assessment, $vital_signs, $assigned_by);
        $stmt->execute();
        
    }
    
    return "Successfully created $count dummy patients";
}
?>
