<?php

// ============================================================
// HireWise - Offer Comparison
// Endpoint: Add one offer (PDF or pasted text). Runs the Python
// extractor (scripts/extract_offer.py) via proc_open, the SAME
// pattern upload_resume.php already uses - no new Flask service,
// since this module needs no heavy ML model to keep warm.
//
// Place at: hirewise/api/add_offer.php
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'C:/xampp/apache/logs/error.log');

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}


// ============================================================
// 1. LOAD PROJECT FILES
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// 2. AUTHENTICATION
// ============================================================

require_login();

$user = current_user();

if (!is_array($user) || !isset($user['user_id']) || !is_numeric($user['user_id'])) {
    send_json(['success' => false, 'message' => 'Unable to identify the logged-in user.'], 401);
}

$userId = (int) $user['user_id'];


// ============================================================
// 3. CHECK REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}


// ============================================================
// 4. VALIDATE input_type
// ============================================================

$inputType = $_POST['input_type'] ?? '';

if (!in_array($inputType, ['pdf', 'text'], true)) {
    send_json(['success' => false, 'message' => 'Invalid or missing input type. Expected pdf or text.'], 400);
}

$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
$MAX_TEXT_LENGTH = 6000;

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    error_log('Unable to locate HireWise project root.');
    send_json(['success' => false, 'message' => 'Server configuration error.'], 500);
}


// ============================================================
// 5. HANDLE INPUT: PDF or TEXT
// ============================================================

$pdfPath = null;         // only set for input_type = pdf
$originalFilename = null;
$pastedText = null;      // only set for input_type = text

if ($inputType === 'pdf') {

    if (!isset($_FILES['file'])) {
        send_json(['success' => false, 'message' => 'No PDF file received.'], 400);
    }

    $file = $_FILES['file'];

    if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log('Offer PDF upload error code: ' . ($file['error'] ?? -1));
        send_json(['success' => false, 'message' => 'File upload failed. Please try again.'], 400);
    }

    if ($file['size'] <= 0 || $file['size'] > $MAX_FILE_SIZE) {
        send_json(['success' => false, 'message' => 'PDF must be a non-empty file under 5 MB.'], 400);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        send_json(['success' => false, 'message' => 'Only PDF files are allowed.'], 400);
    }

    // Real file signature check, not just extension/MIME (both
    // trivially spoofable) - same pattern as upload_resume.php.
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        send_json(['success' => false, 'message' => 'Unable to read the uploaded file.'], 400);
    }
    $signature = fread($handle, 5);
    fclose($handle);

    if ($signature !== '%PDF-') {
        send_json(['success' => false, 'message' => 'The uploaded file is not a valid PDF.'], 400);
    }

    // Private storage, outside the web root - same pattern as
    // Recruitment Analysis. The file is deleted right after
    // extraction (step 8 below); only the extracted fields persist.
    define('OFFER_PRIVATE_DIR', 'C:\\Users\\shreya\\hirewise_private\\offers_tmp\\');

    if (!is_dir(OFFER_PRIVATE_DIR) && !mkdir(OFFER_PRIVATE_DIR, 0775, true)) {
        error_log('Unable to create offers private storage directory.');
        send_json(['success' => false, 'message' => 'Server storage is not configured correctly.'], 500);
    }

    try {
        $uniquePart = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Unable to generate a storage filename.'], 500);
    }

    $originalFilename = $file['name'];
    $storedFilename = 'offer_' . $userId . '_' . time() . '_' . $uniquePart . '.pdf';
    $pdfPath = OFFER_PRIVATE_DIR . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $pdfPath)) {
        error_log('Unable to move uploaded offer PDF to: ' . $pdfPath);
        send_json(['success' => false, 'message' => 'Unable to save the uploaded PDF.'], 500);
    }

} else { // input_type === 'text'

    $pastedText = trim($_POST['text'] ?? '');

    if ($pastedText === '') {
        send_json(['success' => false, 'message' => 'Pasted text cannot be empty.'], 400);
    }

    if (mb_strlen($pastedText) > $MAX_TEXT_LENGTH) {
        send_json(['success' => false, 'message' => 'Pasted text exceeds the 6000 character limit.'], 400);
    }
}


// ============================================================
// 6. CONFIGURE PYTHON EXTRACTOR (.venv311 - already has pdfplumber
//    and spaCy installed; nothing new added to this environment)
// ============================================================

$pythonPath = 'C:\\Users\\shreya\\hirewise\\.venv311\\Scripts\\python.exe';
$scriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'extract_offer.py';

if (!is_file($pythonPath) || !is_file($scriptPath)) {
    error_log('Python executable or extract_offer.py not found. Python: ' . $pythonPath . ' Script: ' . $scriptPath);
    if ($pdfPath) @unlink($pdfPath);
    send_json(['success' => false, 'message' => 'Offer extraction is not configured correctly.'], 500);
}

$command = $inputType === 'pdf'
    ? [$pythonPath, $scriptPath, 'pdf', $pdfPath]
    : [$pythonPath, $scriptPath, 'text'];

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$pipes = [];
$process = proc_open($command, $descriptors, $pipes, $projectRoot, null, ['bypass_shell' => true]);

if (!is_resource($process)) {
    error_log('Unable to start offer extractor.');
    if ($pdfPath) @unlink($pdfPath);
    send_json(['success' => false, 'message' => 'Unable to start offer extraction.'], 500);
}

// For text input, the pasted text is sent over stdin rather than a
// command-line argument - avoids Windows command-line length/quoting
// issues with long, punctuation-heavy pasted text.
if ($inputType === 'text') {
    fwrite($pipes[0], $pastedText);
}
fclose($pipes[0]);

$pythonOutput = stream_get_contents($pipes[1]);
$pythonError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

// The PDF's job is done - extraction has happened (or failed) by
// this point either way. Nothing further needs the file.
if ($pdfPath) {
    @unlink($pdfPath);
}

if ($pythonError !== '') {
    error_log('Offer extractor stderr: ' . $pythonError);
}

if ($exitCode !== 0 && $pythonOutput === '') {
    error_log('Offer extractor exited with code ' . $exitCode . ' and no output.');
    send_json(['success' => false, 'message' => 'Offer extraction failed. Please try again.'], 500);
}


// ============================================================
// 7. DECODE PYTHON OUTPUT
// ============================================================

$extracted = json_decode($pythonOutput, true);

if (!is_array($extracted) || json_last_error() !== JSON_ERROR_NONE) {
    error_log('Invalid JSON from extract_offer.py: ' . $pythonOutput);
    send_json(['success' => false, 'message' => 'Offer extractor returned invalid data.'], 500);
}

if (empty($extracted['success'])) {
    send_json(['success' => false, 'message' => $extracted['message'] ?? 'Unable to extract offer details.'], 422);
}


// ============================================================
// 8. SAVE TO DATABASE
// ============================================================

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        INSERT INTO offers (
            user_id, company_name, job_role, salary_details, location,
            work_mode, benefits, bond_details, notice_period,
            other_conditions, source_type, original_filename
        ) VALUES (
            :user_id, :company_name, :job_role, :salary_details, :location,
            :work_mode, :benefits, :bond_details, :notice_period,
            :other_conditions, :source_type, :original_filename
        )
    ");

    $stmt->execute([
        ':user_id'           => $userId,
        ':company_name'      => $extracted['company_name'] ?? null,
        ':job_role'          => $extracted['job_role'] ?? null,
        ':salary_details'    => $extracted['salary_details'] ?? null,
        ':location'          => $extracted['location'] ?? null,
        ':work_mode'         => $extracted['work_mode'] ?? null,
        ':benefits'          => $extracted['benefits'] ?? null,
        ':bond_details'      => $extracted['bond_details'] ?? null,
        ':notice_period'     => $extracted['notice_period'] ?? null,
        ':other_conditions'  => $extracted['other_conditions'] ?? null,
        ':source_type'       => $inputType,
        ':original_filename' => $originalFilename
    ]);

    $offerId = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Offer save error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to save this offer.'], 500);
}


// ============================================================
// 9. RESPOND
// ============================================================

send_json([
    'success' => true,
    'offer'   => [
        'offer_id'          => $offerId,
        'company_name'      => $extracted['company_name'] ?? null,
        'job_role'          => $extracted['job_role'] ?? null,
        'salary_details'    => $extracted['salary_details'] ?? null,
        'location'          => $extracted['location'] ?? null,
        'work_mode'         => $extracted['work_mode'] ?? null,
        'benefits'          => $extracted['benefits'] ?? null,
        'bond_details'      => $extracted['bond_details'] ?? null,
        'notice_period'     => $extracted['notice_period'] ?? null,
        'other_conditions'  => $extracted['other_conditions'] ?? null,
        'source_type'       => $inputType
    ]
]);