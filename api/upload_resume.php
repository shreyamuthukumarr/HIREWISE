
<?php

// ============================================================
// 1. ERROR LOGGING AND JSON RESPONSE
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'C:/xampp/apache/logs/error.log');

header('Content-Type: application/json; charset=utf-8');

function send_json($data, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


// ============================================================
// 2. LOAD EXISTING PROJECT FILES
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// 3. AUTHENTICATION
// ============================================================

require_login();

$user = current_user();

if (
    !is_array($user) ||
    !isset($user['user_id']) ||
    !is_numeric($user['user_id'])
) {
    send_json([
        'success' => false,
        'message' => 'Unable to identify the logged-in user.'
    ], 401);
}


// ============================================================
// 4. CHECK REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json([
        'success' => false,
        'message' => 'Invalid request method.'
    ], 405);
}


// ============================================================
// 5. CHECK FILE UPLOAD
// ============================================================

if (!isset($_FILES['resume'])) {
    send_json([
        'success' => false,
        'message' => 'No resume file received.'
    ], 400);
}

$file = $_FILES['resume'];

if (
    !isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) ||
    $file['error'] !== UPLOAD_ERR_OK
) {
    $uploadError = $file['error'] ?? -1;

    error_log('Resume upload error code: ' . $uploadError);

    send_json([
        'success' => false,
        'message' => 'File upload failed. Please try again.'
    ], 400);
}


// ============================================================
// 6. VALIDATE SIZE AND PDF TYPE
// ============================================================

$maxFileSize = 5 * 1024 * 1024;

if ($file['size'] <= 0 || $file['size'] > $maxFileSize) {
    send_json([
        'success' => false,
        'message' => 'Resume must be a non-empty PDF under 5 MB.'
    ], 400);
}

$extension = strtolower(
    pathinfo($file['name'], PATHINFO_EXTENSION)
);

if ($extension !== 'pdf') {
    send_json([
        'success' => false,
        'message' => 'Only PDF files are allowed.'
    ], 400);
}

// Check the actual file signature as well as the extension.
$fileHandle = fopen($file['tmp_name'], 'rb');

if ($fileHandle === false) {
    send_json([
        'success' => false,
        'message' => 'Unable to read the uploaded file.'
    ], 400);
}

$fileSignature = fread($fileHandle, 5);
fclose($fileHandle);

if ($fileSignature !== '%PDF-') {
    send_json([
        'success' => false,
        'message' => 'The uploaded file is not a valid PDF.'
    ], 400);
}


// ============================================================
// 7. CONFIGURE FILE PATHS
// ============================================================

// Absolute paths prevent Apache working-directory issues.
$projectRoot = realpath(__DIR__ . '/..');

if ($projectRoot === false) {
    error_log('Unable to locate HireWise project root.');

    send_json([
        'success' => false,
        'message' => 'Server configuration error.'
    ], 500);
}

$uploadDirectory = $projectRoot . DIRECTORY_SEPARATOR .
                   'uploads' . DIRECTORY_SEPARATOR .
                   'resumes';

if (
    !is_dir($uploadDirectory) &&
    !mkdir($uploadDirectory, 0775, true)
) {
    error_log('Unable to create resume upload directory.');

    send_json([
        'success' => false,
        'message' => 'Unable to prepare resume storage.'
    ], 500);
}

if (!is_writable($uploadDirectory)) {
    error_log('Resume upload directory is not writable.');

    send_json([
        'success' => false,
        'message' => 'Resume storage is not writable.'
    ], 500);
}


// ============================================================
// 8. GENERATE UNIQUE FILE NAME AND SAVE PDF
// ============================================================

$userId = (int) $user['user_id'];

try {
    $uniquePart = bin2hex(random_bytes(8));
} catch (Throwable $e) {
    error_log('Filename generation error: ' . $e->getMessage());

    send_json([
        'success' => false,
        'message' => 'Unable to generate a resume filename.'
    ], 500);
}

$newFileName = 'resume_' . $userId . '_' .
               time() . '_' . $uniquePart . '.pdf';

$destination = $uploadDirectory . DIRECTORY_SEPARATOR .
               $newFileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log('Unable to move uploaded resume to: ' . $destination);

    send_json([
        'success' => false,
        'message' => 'Unable to save uploaded resume.'
    ], 500);
}


// ============================================================
// 9. CONFIGURE PYTHON ANALYZER
// ============================================================

$pythonPath = 'C:\\Users\\shreya\\hirewise\\.venv311\\Scripts\\python.exe';

$scriptPath = $projectRoot . DIRECTORY_SEPARATOR .
              'scripts' . DIRECTORY_SEPARATOR .
              'analyze_resume.py';

if (!is_file($pythonPath) || !is_file($scriptPath)) {
    error_log(
        'Python executable or analyzer script not found. ' .
        'Python: ' . $pythonPath .
        ' Script: ' . $scriptPath
    );

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Resume analysis is not configured correctly.'
    ], 500);
}


// ============================================================
// 10. RUN PYTHON AND CAPTURE OUTPUT
// ============================================================

// Use proc_open so stdout (JSON) and stderr (diagnostics)
// are captured separately. This prevents Python warnings from
// corrupting the JSON returned by the analyzer.

$command = [
    $pythonPath,
    $scriptPath,
    $destination
];

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$pipes = [];

$process = proc_open(
    $command,
    $descriptors,
    $pipes,
    $projectRoot,
    null,
    ['bypass_shell' => true]
);

if (!is_resource($process)) {
    error_log('Unable to start Python analyzer.');

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Unable to start resume analysis.'
    ], 500);
}

fclose($pipes[0]);

$pythonOutput = stream_get_contents($pipes[1]);
$pythonError = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$pythonExitCode = proc_close($process);

if ($pythonError !== '') {
    error_log('Resume analyzer stderr: ' . $pythonError);
}

if ($pythonExitCode !== 0) {
    error_log(
        'Python analyzer exited with code ' .
        $pythonExitCode .
        '. Output: ' . $pythonOutput
    );

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Resume analysis failed. Please try another PDF.'
    ], 422);
}


// ============================================================
// 11. DECODE AND VALIDATE PYTHON JSON
// ============================================================

$analysis = json_decode($pythonOutput, true);

if (
    !is_array($analysis) ||
    json_last_error() !== JSON_ERROR_NONE
) {
    error_log(
        'Invalid Python JSON: ' .
        json_last_error_msg() .
        '. Raw output: ' . $pythonOutput
    );

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Resume analyzer returned invalid data.'
    ], 500);
}

if (empty($analysis['success'])) {
    $analysisMessage = $analysis['message'] ??
                       'The PDF could not be analyzed.';

    error_log('Resume analysis unsuccessful: ' . $analysisMessage);

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => $analysisMessage
    ], 422);
}


// ============================================================
// 12. NORMALIZE EXTRACTED FIELDS
// ============================================================

// Make sure every expected field has a predictable type.

$skills = $analysis['skills'] ?? [];

if (!is_array($skills)) {
    $skills = [];
}

$skillsJson = json_encode(
    array_values($skills),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($skillsJson === false) {
    error_log('Unable to encode extracted skills.');

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Unable to process extracted skills.'
    ], 500);
}

$education = is_string($analysis['education'] ?? null)
    ? trim($analysis['education'])
    : '';

$experience = is_string($analysis['experience'] ?? null)
    ? trim($analysis['experience'])
    : '';

$projects = is_string($analysis['projects'] ?? null)
    ? trim($analysis['projects'])
    : '';

$certifications = is_string($analysis['certifications'] ?? null)
    ? trim($analysis['certifications'])
    : '';


// ============================================================
// 13. INSERT INTO MYSQL
// ============================================================

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        INSERT INTO resumes (
            user_id,
            file_name,
            skills,
            education,
            experience,
            projects,
            certifications
        )
        VALUES (
            :user_id,
            :file_name,
            :skills,
            :education,
            :experience,
            :projects,
            :certifications
        )
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':file_name' => $newFileName,
        ':skills' => $skillsJson,
        ':education' => $education,
        ':experience' => $experience,
        ':projects' => $projects,
        ':certifications' => $certifications
    ]);

    $resumeId = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Resume database error: ' . $e->getMessage());

    @unlink($destination);

    send_json([
        'success' => false,
        'message' => 'Unable to save resume details to the database.'
    ], 500);
}


// ============================================================
// 14. RETURN SUCCESS RESPONSE
// ============================================================

send_json([
    'success' => true,
    'message' => 'Resume uploaded and analyzed successfully.',
    'resume_id' => $resumeId,
    'file_name' => $newFileName,
    'original_file_name' => $file['name'],
    'skills' => $skills,
    'skill_count' => count($skills),
    'education' => $education,
    'experience' => $experience,
    'projects' => $projects,
    'certifications' => $certifications
]);

?>