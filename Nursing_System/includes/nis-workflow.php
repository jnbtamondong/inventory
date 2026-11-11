<?php
/** Pure movement policy shared by the server and rendered action menus. */
function nisDepartment(string $department): string {
    return match (strtoupper(trim($department))) {
        'MICU', 'ICU / MICU' => 'ICU',
        'OB/PEDIA WARD', 'OB_PEDIA_WARD', '2ND FLOOR WARD' => 'WARD_2F',
        'MEDICAL/SURGERY WARD', 'MEDICAL_SURGERY_WARD', '3RD FLOOR WARD' => 'WARD_3F',
        'LR/DR', 'LR-DR' => 'LR_DR',
        default => strtoupper(trim($department)),
    };
}
function nisDepartmentLabel(string $department): string {
    return [
        'ER'=>'Emergency Room', 'OPD'=>'Outpatient Department',
        'WARD_2F'=>'OB/Pedia Ward', 'WARD_3F'=>'Medical/Surgery Ward',
        'WARD'=>'Ward (receiving unit required)', 'OR'=>'Operating Room',
        'LR_DR'=>'Labor Room / Delivery Room', 'ICU'=>'MICU', 'NICU'=>'NICU',
    ][nisDepartment($department)] ?? $department;
}
function nisMovementTransitions(string $status): array {
    return match ($status) {
        'PENDING'=>['ACCEPTED','REJECTED','CANCELLED'],
        'ACCEPTED'=>['IN TRANSIT','CANCELLED'],
        'IN TRANSIT'=>['RECEIVED'],
        // Historical terminal states stay terminal; do not rewrite their history.
        default=>[],
    };
}
function nisMovementActionSide(string $status): string {
    return in_array($status, ['ACCEPTED','REJECTED','RECEIVED'], true) ? 'destination' : 'source';
}
function nisMovementCanAct(array $movement, string $next): bool {
    if (!in_array($next, nisMovementTransitions($movement['status']), true)) return false;
    $department = nisDepartment((string)$movement[nisMovementActionSide($next) . '_department']);
    // Generic WARD rows cannot authorize a receiver until the exact ward is resolved.
    if ($department === 'WARD') return false;
    return hasPermission('patient_movements', 'edit', $department);
}

/** Resolve legacy WARD only from the patient's existing specialty, never from user input. */
function nisPatientWard(array $patient): string {
    return in_array($patient['ward_department'] ?? '', ['OB','PEDIA','NEWBORN'], true) ? 'WARD_2F' : 'WARD_3F';
}
function nisBedMatchesDepartment(array $bed, string $department): bool {
    $department = nisDepartment($department);
    if ($department === 'ER') return $bed['bed_type'] === 'Emergency' && str_starts_with($bed['room_number'], 'ER-');
    if ($bed['bed_type'] !== 'General') return false;
    if ($department === 'WARD_3F') return (bool)preg_match('/^WARD-0[34]/', $bed['room_number']);
    return $department === 'WARD_2F' && in_array($bed['room_number'], obPediaWardRoomNumbers(), true);
}

function nisRequestMovement(mysqli $conn, int $patientId, string $encounter, string $source, string $destination, string $type, string $reason, string $remarks): void {
    $outer=(bool)$conn->query('SELECT @@in_transaction AS active')->fetch_assoc()['active'];
    if ($outer) $conn->query('SAVEPOINT nis_request'); else $conn->begin_transaction();
    try {
        $stmt=$conn->prepare('SELECT p.*,b.bed_type,b.room_number FROM patients p LEFT JOIN beds b ON b.bed_id=p.current_bed WHERE p.patient_id=? FOR UPDATE');
        $stmt->bind_param('i',$patientId);$stmt->execute();$patient=$stmt->get_result()->fetch_assoc();
        if (!$patient || $encounter==='' || (string)$patient['patient_code']!==$encounter || in_array($patient['status'],inactivePatientStatuses(),true)) {
            throw new DomainException('Select an active patient and verify the encounter.');
        }
        $source=nisDepartment($source);$destination=nisDepartment($destination);
        if ($source==='WARD') $source=nisPatientWard($patient);
        if ($destination==='WARD') $destination=nisPatientWard($patient);
        $units=['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'];
        if (!in_array($source,$units,true) || !in_array($destination,$units,true) || $source===$destination) throw new DomainException('Select different sending and receiving clinical departments.');
        if (!hasPermission('patient_movements','edit',$source)) throw new DomainException('You cannot send patients from this department.');
        if (trim($reason)==='') throw new DomainException('Record the transfer reason.');
        if (in_array($source,['WARD_2F','WARD_3F'],true)) {
            if ($patient['status']!=='admit_inpatient' || nisPatientWard($patient)!==$source || !nisBedMatchesDepartment($patient,$source)) throw new DomainException('Verify the sending ward and occupied bed before requesting transfer.');
        } elseif ($source==='ER') {
            if (!in_array($patient['status'],array_merge(activePatientStatuses(),['for_admission']),true) || ($patient['current_bed'] && !nisBedMatchesDepartment($patient,'ER'))) throw new DomainException('The encounter is not in the ER workflow.');
            // Bedless outpatient records must not be treated as ER patients.
            $stmt=$conn->prepare("SELECT id FROM opd_records WHERE patient_id=? AND encounter_id=?");
            $stmt->bind_param('is',$patientId,$encounter);$stmt->execute();
            if ($stmt->get_result()->num_rows) throw new DomainException('Verify OPD disposition before sending this encounter from ER.');
        } else {
            // Existing unit documentation is the source of truth for specialty intake.
            $tables=['ICU'=>['micu_records',"status='ACTIVE'"],'NICU'=>['nicu_records',"status='ACTIVE'"],
                'OR'=>['or_schedule',"status IN ('ACCEPTED','IN OR','RECOVERY')"],
                'LR_DR'=>['delivery_room_records','1=1'],'OPD'=>['opd_records','1=1']];
            [$table,$condition]=$tables[$source];
            $stmt=$conn->prepare("SELECT id FROM $table WHERE patient_id=? AND encounter_id=? AND $condition FOR UPDATE");
            $stmt->bind_param('is',$patientId,$encounter);$stmt->execute();
            if (!$stmt->get_result()->num_rows) throw new DomainException('The sending unit has no matching active clinical intake.');
        }
        $stmt=$conn->prepare("SELECT id FROM patient_movements WHERE patient_id=? AND status IN ('PENDING','ACCEPTED','IN TRANSIT','ARRIVED') FOR UPDATE");
        $stmt->bind_param('i',$patientId);$stmt->execute();
        if ($stmt->get_result()->num_rows) throw new DomainException('An active movement already exists. Complete or cancel it first.');
        $userId=(int)($_SESSION['user_id']??0);
        $stmt=$conn->prepare("INSERT INTO patient_movements (patient_id,encounter_id,from_department,to_department,source_department,previous_department,current_department,destination_department,movement_type,reason,status,requested_by,movement_date_time,remarks,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,'PENDING',?,NOW(),?,?)");
        $stmt->bind_param('isssssssssisi',$patientId,$encounter,$source,$destination,$source,$source,$source,$destination,$type,$reason,$userId,$remarks,$userId);$stmt->execute();
        $action='Transfer Requested';$description=$source.' to '.$destination.'; encounter '.$encounter.'; '.$reason;
        $stmt=$conn->prepare('INSERT INTO patient_tracking (patient_id,action,description,from_status,to_status,user_id) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('issssi',$patientId,$action,$description,$source,$destination,$userId);$stmt->execute();
        if ($outer) $conn->query('RELEASE SAVEPOINT nis_request'); else $conn->commit();
    } catch (Throwable $e) {
        if ($outer) $conn->query('ROLLBACK TO SAVEPOINT nis_request'); else $conn->rollback();
        throw $e;
    }
}

/** One transaction for receiving, bed ownership, patient state and movement history.
 * Specialized intake remains mandatory for OR, LR-DR, MICU and NICU.
 */
function nisTransitionMovement(mysqli $conn, int $id, string $next, string $remarks, int $bedId = 0): void {
    $conn->begin_transaction();
    try {
        // Always lock patient before movement/bed: concurrent requests use the same order.
        $stmt=$conn->prepare('SELECT patient_id FROM patient_movements WHERE id=?');
        $stmt->bind_param('i',$id); $stmt->execute();
        $reference=$stmt->get_result()->fetch_assoc();
        if (!$reference) throw new DomainException('Movement record not found.');
        $patientId=(int)$reference['patient_id'];
        $stmt=$conn->prepare('SELECT * FROM patients WHERE patient_id=? FOR UPDATE');
        $stmt->bind_param('i',$patientId); $stmt->execute(); $patient=$stmt->get_result()->fetch_assoc();
        $stmt=$conn->prepare('SELECT * FROM patient_movements WHERE id=? FOR UPDATE');
        $stmt->bind_param('i',$id); $stmt->execute(); $movement=$stmt->get_result()->fetch_assoc();
        if (!$patient || !$movement || (string)$patient['patient_code'] !== (string)$movement['encounter_id']) {
            throw new DomainException('The movement does not match the patient encounter.');
        }
        if (!nisMovementCanAct($movement,$next)) throw new DomainException('This transition is unavailable for your department or the current status. Refresh the list.');
        if (in_array($patient['status'], inactivePatientStatuses(), true)) throw new DomainException('A closed encounter cannot be transferred.');
        if (in_array($next,['REJECTED','CANCELLED','IN TRANSIT'],true) && trim($remarks)==='') {
            throw new DomainException($next==='IN TRANSIT' ? 'Record the sending nurse handover before transport.' : 'A reason is required.');
        }
        $destination=nisDepartment($movement['destination_department']);
        if ($next==='RECEIVED') {
            if (!in_array($destination,['WARD_2F','WARD_3F','ER'],true)) {
                throw new DomainException('Complete and verify the receiving unit clinical intake first. Central receiving currently supports ER and the two wards.');
            }
            if ($bedId<=0) throw new DomainException('Select the receiving bed.');
            $stmt=$conn->prepare('SELECT * FROM beds WHERE bed_id=? FOR UPDATE');
            $stmt->bind_param('i',$bedId);$stmt->execute();$bed=$stmt->get_result()->fetch_assoc();
            if (!$bed || !nisBedMatchesDepartment($bed,$destination) || $bed['status']!=='available' || $bed['patient_id']!==null) {
                throw new DomainException('The receiving bed is unavailable or belongs to another department.');
            }
            $stmt=$conn->prepare('SELECT patient_id FROM patients WHERE current_bed=? AND patient_id<>?');
            $stmt->bind_param('ii',$bedId,$patientId);$stmt->execute();
            if ($stmt->get_result()->num_rows) throw new DomainException('The bed is already assigned to another encounter.');
            // Critical units use separate bed ledgers. Do not silently leave them occupied.
            foreach (['micu_records','nicu_records'] as $table) {
                $stmt=$conn->prepare("SELECT id FROM $table WHERE patient_id=? AND status='ACTIVE' FOR UPDATE");
                $stmt->bind_param('i',$patientId);$stmt->execute();
                if ($stmt->get_result()->num_rows) throw new DomainException('Complete the critical-care unit transfer-out record before central receiving.');
            }
            if ($patient['current_bed']) {
                $oldBed=(int)$patient['current_bed'];
                $stmt=$conn->prepare("UPDATE beds SET patient_id=NULL,status='available' WHERE bed_id=? AND patient_id=? AND status='occupied'");
                $stmt->bind_param('ii',$oldBed,$patientId);$stmt->execute();
                if ($stmt->affected_rows!==1) throw new DomainException('The sending bed ownership is inconsistent. Verify the bed ledger first.');
            }
            $stmt=$conn->prepare("UPDATE beds SET patient_id=?,status='occupied' WHERE bed_id=?");
            $stmt->bind_param('ii',$patientId,$bedId);$stmt->execute();
            $status=$destination==='ER'?'triage':'admit_inpatient';
            if ($destination!=='ER' && nisPatientWard($patient)!==$destination) {
                throw new DomainException('The receiving ward does not match the recorded patient specialty. Verify the admission record first.');
            }
            $stmt=$conn->prepare('UPDATE patients SET current_bed=?,status=? WHERE patient_id=?');
            $stmt->bind_param('isi',$bedId,$status,$patientId);$stmt->execute();
        }
        $userId=(int)($_SESSION['user_id']??0);
        $entry=date('Y-m-d H:i:s').' · '.$next.' · User '.$userId.($remarks!==''?' · '.$remarks:'');
        $stmt=$conn->prepare("UPDATE patient_movements SET status=?,accepted_by=IF(?='ACCEPTED',?,accepted_by),current_department=IF(?='RECEIVED',destination_department,current_department),movement_date_time=NOW(),remarks=CONCAT_WS('\n',NULLIF(remarks,''),?) WHERE id=?");
        $stmt->bind_param('ssissi',$next,$next,$userId,$next,$entry,$id);$stmt->execute();
        $action='Transfer '.$next; $description='Movement #'.$id.'; encounter '.$movement['encounter_id'].'; '.$entry;
        $stmt=$conn->prepare('INSERT INTO patient_tracking (patient_id,action,description,from_status,to_status,user_id) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('issssi',$patientId,$action,$description,$movement['status'],$next,$userId);$stmt->execute();
        require_once __DIR__.'/er-live-tracker.php';
        erTrackerObserve($conn,$patientId,$userId,'movement_'.$next);
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}
