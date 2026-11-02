<?php

// ============================================================
// HireWise - Recruitment Analysis
// Endpoint: Receive ONE input (PDF, image, or text), call the
// Flask analysis service (extraction + MiniLM model + rule-based
// checks), store the result, and return the full risk report.
//
// Requires recruitment_api.py (Phase 6) running on port 5001.
//
// Place at: hirewise/api/analyze_recruitment.php
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
    send_json([
        'success' => false,
        'message' => 'Unable to identify the logged-in user.'
    ], 401);
}

$userId = (int) $user['user_id'];


// ============================================================
// 3. CHECK REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json([
        'success' => false,
        'message' => 'Invalid request method.'
    ], 405);
}


// ============================================================
// 4. VALIDATE input_type
// ============================================================

$inputType = $_POST['input_type'] ?? '';

if (!in_array($inputType, ['pdf', 'image', 'text'], true)) {
    send_json([
        'success' => false,
        'message' => 'Invalid or missing input type. Expected pdf, image, or text.'
    ], 400);
}

$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB, matches the frontend
$MAX_TEXT_LENGTH = 6000;          // matches the frontend textarea limit


// ============================================================
// 5. PREPARE PRIVATE STORAGE DIRECTORY (outside the web root)
// ============================================================
// Recruitment content is more sensitive than resumes, so unlike
// uploads/resumes/, this folder is NOT inside hirewise/ at all -
// Apache cannot serve it under any URL, by construction.

define('RECRUITMENT_PRIVATE_DIR', 'C:\\Users\\shreya\\hirewise_private\\recruitment_tmp\\');

if (!is_dir(RECRUITMENT_PRIVATE_DIR) && !mkdir(RECRUITMENT_PRIVATE_DIR, 0775, true)) {
    error_log('Unable to create recruitment private storage directory: ' . RECRUITMENT_PRIVATE_DIR);
    send_json([
        'success' => false,
        'message' => 'Server storage is not configured correctly.'
    ], 500);
}

if (!is_writable(RECRUITMENT_PRIVATE_DIR)) {
    error_log('Recruitment private storage directory is not writable: ' . RECRUITMENT_PRIVATE_DIR);
    send_json([
        'success' => false,
        'message' => 'Server storage is not writable.'
    ], 500);
}

try {
    $uniquePart = bin2hex(random_bytes(8));
} catch (Throwable $e) {
    error_log('Filename generation error: ' . $e->getMessage());
    send_json([
        'success' => false,
        'message' => 'Unable to generate a storage filename.'
    ], 500);
}

$storedFilename = null;      // filename saved on disk (all types)
$originalFilename = null;    // the name the user's file had, if any


// ============================================================
// 6. HANDLE EACH INPUT TYPE
// ============================================================

if ($inputType === 'pdf') {

    // --------------------------------------------------------
    // 6a. PDF validation (same pattern as upload_resume.php)
    // --------------------------------------------------------

    if (!isset($_FILES['file'])) {
        send_json(['success' => false, 'message' => 'No PDF file received.'], 400);
    }

    $file = $_FILES['file'];

    if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log('Recruitment PDF upload error code: ' . ($file['error'] ?? -1));
        send_json(['success' => false, 'message' => 'File upload failed. Please try again.'], 400);
    }

    if ($file['size'] <= 0 || $file['size'] > $MAX_FILE_SIZE) {
        send_json(['success' => false, 'message' => 'PDF must be a non-empty file under 5 MB.'], 400);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($extension !== 'pdf') {
        send_json(['success' => false, 'message' => 'Only PDF files are allowed.'], 400);
    }

    // Check the real file signature, not just the extension/MIME
    // type the browser reports (both are trivial to fake).
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        send_json(['success' => false, 'message' => 'Unable to read the uploaded file.'], 400);
    }
    $signature = fread($handle, 5);
    fclose($handle);

    if ($signature !== '%PDF-') {
        send_json(['success' => false, 'message' => 'The uploaded file is not a valid PDF.'], 400);
    }

    $originalFilename = $file['name'];
    $storedFilename = 'rec_' . $userId . '_' . time() . '_' . $uniquePart . '.pdf';
    $destination = RECRUITMENT_PRIVATE_DIR . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log('Unable to move uploaded recruitment PDF to: ' . $destination);
        send_json(['success' => false, 'message' => 'Unable to save the uploaded PDF.'], 500);
    }

} elseif ($inputType === 'image') {

    // --------------------------------------------------------
    // 6b. Image validation
    // --------------------------------------------------------

    if (!isset($_FILES['file'])) {
        send_json(['success' => false, 'message' => 'No image file received.'], 400);
    }

    $file = $_FILES['file'];

    if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log('Recruitment image upload error code: ' . ($file['error'] ?? -1));
        send_json(['success' => false, 'message' => 'File upload failed. Please try again.'], 400);
    }

    if ($file['size'] <= 0 || $file['size'] > $MAX_FILE_SIZE) {
        send_json(['success' => false, 'message' => 'Image must be a non-empty file under 5 MB.'], 400);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        send_json(['success' => false, 'message' => 'Only JPG and PNG images are allowed.'], 400);
    }

    // Check real magic bytes: JPEG starts FF D8 FF, PNG starts
    // 89 50 4E 47 0D 0A 1A 0A. This catches a renamed .exe or a
    // mismatched extension that the browser's MIME type missed.
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        send_json(['success' => false, 'message' => 'Unable to read the uploaded file.'], 400);
    }
    $header = fread($handle, 8);
    fclose($handle);

    $isJpeg = substr($header, 0, 3) === "\xFF\xD8\xFF";
    $isPng  = substr($header, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";

    if (!$isJpeg && !$isPng) {
        send_json(['success' => false, 'message' => 'The uploaded file is not a valid JPG or PNG image.'], 400);
    }

    $originalFilename = $file['name'];
    $storedFilename = 'rec_' . $userId . '_' . time() . '_' . $uniquePart . '.' . $extension;
    $destination = RECRUITMENT_PRIVATE_DIR . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log('Unable to move uploaded recruitment image to: ' . $destination);
        send_json(['success' => false, 'message' => 'Unable to save the uploaded image.'], 500);
    }

} else { // $inputType === 'text'

    // --------------------------------------------------------
    // 6c. Pasted text validation
    // --------------------------------------------------------

    $text = $_POST['text'] ?? '';
    $text = trim($text);

    if ($text === '') {
        send_json(['success' => false, 'message' => 'Pasted text cannot be empty.'], 400);
    }

    if (mb_strlen($text) > $MAX_TEXT_LENGTH) {
        send_json(['success' => false, 'message' => 'Pasted text exceeds the 6000 character limit.'], 400);
    }

    // Stored as a .txt file so Phase 4 can read every input type
    // (pdf/image/text) the same way: one file path per analysis.
    $storedFilename = 'rec_' . $userId . '_' . time() . '_' . $uniquePart . '.txt';
    $destination = RECRUITMENT_PRIVATE_DIR . $storedFilename;

    if (file_put_contents($destination, $text) === false) {
        error_log('Unable to write recruitment text input to: ' . $destination);
        send_json(['success' => false, 'message' => 'Unable to save the submitted text.'], 500);
    }
}


// ============================================================
// 7. INSERT A "PENDING" ROW
// ============================================================
// Later phases (extraction, model, rules) will read this row,
// process the stored file, and update it with results.

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        INSERT INTO recruitment_analyses (
            user_id, input_type, source_filename, original_filename, status
        )
        VALUES (
            :user_id, :input_type, :source_filename, :original_filename, 'pending'
        )
    ");

    $stmt->execute([
        ':user_id'           => $userId,
        ':input_type'        => $inputType,
        ':source_filename'   => $storedFilename,
        ':original_filename' => $originalFilename
    ]);

    $analysisId = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Recruitment analysis database error: ' . $e->getMessage());

    // Roll back the file we just saved, so nothing orphaned is
    // left in private storage if the DB write failed.
    @unlink(RECRUITMENT_PRIVATE_DIR . $storedFilename);

    send_json(['success' => false, 'message' => 'Unable to save the analysis request.'], 500);
}


// ============================================================
// 8. CALL THE RECRUITMENT ANALYSIS SERVICE (Flask, port 5001)
// ============================================================
// Same pattern as get-readiness.php's call to the readiness model
// on port 5000: one HTTP call, JSON in, JSON out. The Flask service
// already has MiniLM, the classifier, and EasyOCR loaded in memory,
// so this call does extraction + model scoring + rule checks in
// one round trip.

const RECRUITMENT_API_URL = 'http://127.0.0.1:5001/analyze';

$storedFilePath = RECRUITMENT_PRIVATE_DIR . $storedFilename;

$ch = curl_init(RECRUITMENT_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'input_type' => $inputType,
        'file_path'  => $storedFilePath
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    // OCR + embedding can genuinely take a few seconds, unlike the
    // near-instant Random Forest call in get-readiness.php.
    CURLOPT_TIMEOUT        => 30
]);

$responseBody = curl_exec($ch);
$curlError    = curl_error($ch);
$httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    error_log('Recruitment API call failed (no response). ' . $curlError);

    $pdo->prepare("UPDATE recruitment_analyses SET status = 'failed', error_message = :msg WHERE analysis_id = :id")
        ->execute([':msg' => 'Analysis service unreachable.', ':id' => $analysisId]);

    send_json([
        'success' => false,
        'message' => 'The recruitment analysis service is not available right now. '
                    . 'Please make sure recruitment_api.py is running, then try again.'
    ], 503);
}

$analysis = json_decode($responseBody, true);

if (!is_array($analysis) || json_last_error() !== JSON_ERROR_NONE) {
    error_log('Recruitment API returned invalid JSON: ' . $responseBody);

    $pdo->prepare("UPDATE recruitment_analyses SET status = 'failed', error_message = :msg WHERE analysis_id = :id")
        ->execute([':msg' => 'Invalid response from analysis service.', ':id' => $analysisId]);

    // The file has already been read by the Flask service (or the
    // attempt failed) - nothing further to extract from it, and we
    // don't want to retain recruitment content longer than needed.
    @unlink($storedFilePath);

    send_json(['success' => false, 'message' => 'The analysis service returned an unexpected response.'], 502);
}

// The private file's job is done - extraction has already happened
// inside the Flask service by this point, success or failure.
@unlink($storedFilePath);

if (empty($analysis['success'])) {
    $failureMessage = $analysis['message'] ?? 'Analysis failed.';

    $pdo->prepare("UPDATE recruitment_analyses SET status = 'failed', error_message = :msg WHERE analysis_id = :id")
        ->execute([':msg' => $failureMessage, ':id' => $analysisId]);

    send_json(['success' => false, 'analysis_id' => $analysisId, 'message' => $failureMessage], 422);
}


// ============================================================
// 9. STORE THE RESULT
// ============================================================
// Only the risk category, model score, rule indicators (which are
// already short evidence snippets, not full message text) are kept
// long-term - matching the earlier decision not to retain full
// extracted text or the original file any longer than necessary.

$resultForStorage = [
    'indicators'   => $analysis['indicators'] ?? [],
    'rule_summary' => $analysis['rule_summary'] ?? null,
    'note'         => $analysis['note'] ?? null
];

try {
    $updateStmt = $pdo->prepare("
        UPDATE recruitment_analyses
        SET status = 'completed',
            risk_category = :risk_category,
            model_score = :model_score,
            result_json = :result_json
        WHERE analysis_id = :id
    ");

    $updateStmt->execute([
        ':risk_category' => $analysis['risk_category'] ?? null,
        ':model_score'   => $analysis['model_score'] ?? null,
        ':result_json'   => json_encode($resultForStorage, JSON_UNESCAPED_UNICODE),
        ':id'            => $analysisId
    ]);
} catch (PDOException $e) {
    error_log('Recruitment result save error: ' . $e->getMessage());
    // Not fatal to the response - the person still gets their
    // result now, it just wasn't persisted for later viewing.
}


// ============================================================
// 10. RESPOND WITH THE FULL REPORT
// ============================================================
// extracted_text is returned here for THIS response only (it is
// the user's own just-submitted content) but is deliberately never
// written to the database, per the storage decision above.

send_json([
    'success'       => true,
    'analysis_id'   => $analysisId,
    'status'        => 'completed',
    'risk_category' => $analysis['risk_category'] ?? null,
    'model_score'   => $analysis['model_score'] ?? null,
    'indicators'    => $analysis['indicators'] ?? [],
    'rule_summary'  => $analysis['rule_summary'] ?? null,
    'extracted_text'=> $analysis['extracted_text'] ?? '',
    'char_count'    => $analysis['char_count'] ?? 0,
    'warning'       => $analysis['warning'] ?? null,
    'note'          => $analysis['note'] ?? null
]);