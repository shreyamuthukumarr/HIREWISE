
<?php

// ============================================================
// 1. ERROR HANDLING AND JSON RESPONSE
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
// 2. LOAD PROJECT FILES
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

$userId = (int) $user['user_id'];


// ============================================================
// 4. CHECK REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json([
        'success' => false,
        'message' => 'Invalid request method.'
    ], 405);
}


// ============================================================
// 5. FETCH LATEST RESUME FOR LOGGED-IN USER
// ============================================================

try {

    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT
            resume_id,
            file_name,
            skills,
            education,
            experience,
            projects,
            certifications,
            uploaded_at
        FROM resumes
        WHERE user_id = :user_id
        ORDER BY uploaded_at DESC, resume_id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $resume = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resume) {
        send_json([
            'success' => false,
            'message' => 'No resume found. Please upload your resume.'
        ], 404);
    }


    // ========================================================
    // 6. DECODE SKILLS JSON
    // ========================================================

    $skills = json_decode(
        $resume['skills'] ?? '[]',
        true
    );

    if (!is_array($skills)) {
        $skills = [];
    }


    // ========================================================
    // 7. PREPARE RESPONSE
    // ========================================================

    send_json([

        'success' => true,

        'message' => 'Resume details fetched successfully.',

        'resume_id' => (int) $resume['resume_id'],

        'file_name' => $resume['file_name'],

        'skills' => array_values($skills),

        'skill_count' => count($skills),

        'education' => $resume['education'] ?? '',

        'experience' => $resume['experience'] ?? '',

        'projects' => $resume['projects'] ?? '',

        'certifications' => $resume['certifications'] ?? '',

        'uploaded_at' => $resume['uploaded_at']

    ]);

} catch (Throwable $e) {

    error_log(
        'Resume fetch error: ' . $e->getMessage()
    );

    send_json([
        'success' => false,
        'message' => 'Unable to fetch resume details.'
    ], 500);
}

?>