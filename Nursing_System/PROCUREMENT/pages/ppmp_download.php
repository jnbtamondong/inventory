<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$ppmp = db_one('SELECT ppmp_no,status FROM procurement_ppmp WHERE id=?', [$id]);
if (!$ppmp) {
    http_response_code(404);
    exit('PPMP not found.');
}
if ($ppmp['status'] === 'FOR APPROVAL') {
    http_response_code(403);
    exit('PPMPs for approval cannot be downloaded or printed.');
}

$_GET['id'] = $id;
ob_start();
include __DIR__ . '/ppmp_print.php';
$html = (string) ob_get_clean();

$root = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
$toFileUri = static fn(string $path): string => 'file:///' . str_replace('\\', '/', ltrim($path, '\\/'));
$assets = [
    '../assets/ppmp-print.css' => $toFileUri($root . '/PROCUREMENT/assets/ppmp-print.css'),
    '../assets/ppmp-print.js' => $toFileUri($root . '/PROCUREMENT/assets/ppmp-print.js'),
    '../../CSSR_INVENTORY/assets/img/doh-logo.png' => $toFileUri($root . '/CSSR_INVENTORY/assets/img/doh-logo.png'),
    '../../CSSR_INVENTORY/assets/img/jvgh-seal.png' => $toFileUri($root . '/CSSR_INVENTORY/assets/img/jvgh-seal.png'),
];
$html = str_replace(array_keys($assets), array_values($assets), $html);

$edgeCandidates = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
];
$edge = null;
foreach ($edgeCandidates as $candidate) {
    if (is_file($candidate)) { $edge = $candidate; break; }
}
if (!$edge) {
    throw new RuntimeException('Microsoft Edge is required to download the PPMP PDF.');
}

$jobDir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'ppmp_pdf_' . bin2hex(random_bytes(8));
if (!mkdir($jobDir, 0700, true) && !is_dir($jobDir)) {
    throw new RuntimeException('Unable to create the temporary PDF folder.');
}
$htmlPath = $jobDir . DIRECTORY_SEPARATOR . 'ppmp.html';
$pdfPath = $jobDir . DIRECTORY_SEPARATOR . 'ppmp.pdf';
$profilePath = $jobDir . DIRECTORY_SEPARATOR . 'edge-profile';
file_put_contents($htmlPath, $html, LOCK_EX);

$command = [
    $edge,
    '--headless',
    '--disable-gpu',
    '--no-first-run',
    '--allow-file-access-from-files',
    '--no-pdf-header-footer',
    '--run-all-compositor-stages-before-draw',
    '--user-data-dir=' . $profilePath,
    '--print-to-pdf=' . $pdfPath,
    $toFileUri($htmlPath),
];
$pipes = [];
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $jobDir, null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    throw new RuntimeException('Unable to start the PDF generator.');
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
for ($attempt = 0; $attempt < 20 && !is_file($pdfPath); $attempt++) usleep(100000);

if ($exitCode !== 0 || !is_file($pdfPath) || filesize($pdfPath) < 1000) {
    log_error(new RuntimeException('PPMP PDF generation failed: ' . trim($stderr . ' ' . $stdout)), 'PPMP_PDF');
    http_response_code(500);
    exit('Unable to generate the PPMP PDF.');
}

$safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $ppmp['ppmp_no']) ?: 'PPMP';
$pdf = (string) file_get_contents($pdfPath);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeNumber . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;

$removeTree = static function(string $directory): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) @rmdir($entry->getPathname()); else @unlink($entry->getPathname());
    }
    @rmdir($directory);
};
$removeTree($jobDir);
