<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/employee_documents_schema.php';

ensureEmployeeDocumentsSchema($conn);

function createDummyPdf(string $path, string $employee, string $document, string $issued, ?string $expires): void
{
    $escape = static fn(string $value): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    $lines = [
        'DUMMY EMPLOYEE DOCUMENT',
        'Employee: ' . $employee,
        'Document: ' . $document,
        'Date Issued: ' . $issued,
        'Expiration Date: ' . ($expires ?: 'N/A'),
        'FOR SYSTEM TESTING ONLY',
    ];
    $commands = ["BT", "/F1 16 Tf", "50 760 Td"];
    foreach ($lines as $index => $line) {
        if ($index > 0) $commands[] = "0 -28 Td";
        $commands[] = '(' . $escape($line) . ') Tj';
    }
    $commands[] = 'ET';
    $stream = implode("\n", $commands) . "\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "$number 0 obj\n$object\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($index = 1; $index <= count($objects); $index++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    if (file_put_contents($path, $pdf) === false) throw new RuntimeException('Unable to create dummy PDF: ' . $path);
}

$documents = [
    ['Employment Documents', 'Proof of Appointment', '2025-01-15', null],
    ['Employment Documents', 'PDS', '2026-01-31', null],
    ['Professional Documents', 'Diploma', '2020-04-15', null],
    ['Professional Documents', 'PRC License', '2024-06-01', '2027-05-31'],
    ['Professional Documents', 'PNA', '2026-01-01', '2026-12-31'],
    ['Training & Certificates', 'BLS', '2025-10-15', '2026-10-15'],
    ['Training & Certificates', 'ACLS', '2025-09-10', '2026-09-10'],
    ['Training & Certificates', 'ER Training', '2026-06-01', null],
    ['Training & Certificates', 'OR Training', '2026-07-01', null],
    ['Training & Certificates', 'DR Training', '2026-08-01', null],
    ['Training & Certificates', 'NICU Training', '2026-09-01', null],
    ['Training & Certificates', 'ICU Training', '2025-10-01', null],
    ['Training & Certificates', 'Other Trainings', '2025-11-01', null],
];

$users = $conn->query("SELECT user_id,username,full_name FROM users WHERE username IN ('dummy.chief','dummy.assistant','dummy.supervisor','dummy.headnurse','dummy.secretary') ORDER BY user_id")->fetch_all(MYSQLI_ASSOC);
$exists = $conn->prepare("SELECT id FROM nursing_employee_documents WHERE employee_id=? AND document_type=? AND document_status<>'SUPERSEDED' AND archived_at IS NULL LIMIT 1");
$insert = $conn->prepare("INSERT INTO nursing_employee_documents(employee_id,category,document_type,file_name,original_name,issue_date,expiry_date,document_status,version_no,verification_status,notes,uploaded_by) VALUES(?,?,?,?,?,?,?,'ACTIVE',1,'FOR VERIFICATION','Dummy test document',?)");
$created = 0;

foreach ($users as $user) {
    $employeeId = (int) $user['user_id'];
    foreach ($documents as [$category, $document, $issued, $expires]) {
        $exists->bind_param('is', $employeeId, $document);
        $exists->execute();
        if ($exists->get_result()->fetch_assoc()) continue;
        $folder = preg_replace('/[^a-z0-9]+/', '_', strtolower($category));
        $directory = __DIR__ . '/../storage/employee_documents/' . $employeeId . '/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0750, true)) throw new RuntimeException('Unable to create dummy document directory.');
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($document)), '_');
        $stored = 'dummy_' . $slug . '.pdf';
        $relative = $employeeId . '/' . $folder . '/' . $stored;
        createDummyPdf($directory . '/' . $stored, $user['full_name'], $document, $issued, $expires);
        $original = $user['username'] . '_' . $slug . '.pdf';
        $insert->bind_param('issssssi', $employeeId, $category, $document, $relative, $original, $issued, $expires, $employeeId);
        $insert->execute();
        $created++;
    }
}

echo "Created $created dummy employee documents.\n";
