<?php
/**
 * HireWise - Module 3 (Placement Readiness)
 * Endpoint: Get Readiness
 *
 * Flow:
 *   1. Read the logged-in student's latest resume (READ ONLY)
 *   2. Average their completed assessment attempts per category
 *   3. Send the six features to the Flask API
 *   4. Return the prediction + supporting breakdown as JSON
 *
 * Place at: hirewise/api/get-readiness.php
 * Also needs: hirewise/includes/readiness_features.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/readiness_features.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

const READINESS_API_URL = 'http://127.0.0.1:5000/predict';

$pdo  = get_db_connection();
$user = current_user();

try {
    // ---------------------------------------------------------
    // 1. Latest resume for this student
    // ---------------------------------------------------------

    $resumeStmt = $pdo->prepare(
        'SELECT skills, experience, projects, certifications
         FROM resumes
         WHERE user_id = :user_id
         ORDER BY uploaded_at DESC, resume_id DESC
         LIMIT 1'
    );
    $resumeStmt->execute(['user_id' => $user['user_id']]);
    $resume = $resumeStmt->fetch();

    // ---------------------------------------------------------
    // 2. Average score per category across completed attempts.
    //    Each attempt is scored per category first, straight from
    //    the answers, so "full" attempts count toward BOTH aptitude
    //    and technical. Every attempt then counts equally.
    // ---------------------------------------------------------

    $scoreStmt = $pdo->prepare(
        'SELECT category, AVG(attempt_score) AS avg_score, COUNT(*) AS attempts
         FROM (
             SELECT a.attempt_id, q.category,
                    AVG(ans.is_correct) * 100 AS attempt_score
             FROM assessment_attempts a
             JOIN attempt_answers ans ON ans.attempt_id = a.attempt_id
             JOIN questions q ON q.question_id = ans.question_id
             WHERE a.user_id = :user_id AND a.status = "completed"
             GROUP BY a.attempt_id, q.category
         ) AS per_attempt
         GROUP BY category'
    );
    $scoreStmt->execute(['user_id' => $user['user_id']]);

    $scores = [];
    foreach ($scoreStmt->fetchAll() as $row) {
        $scores[$row['category']] = [
            'avg'      => (float)$row['avg_score'],
            'attempts' => (int)$row['attempts']
        ];
    }

    // ---------------------------------------------------------
    // 3. Not enough data yet? Say what's missing instead of guessing.
    // ---------------------------------------------------------

    $missing = [];

    if (!$resume) {
        $missing[] = 'resume';
    }
    if (!isset($scores['aptitude'])) {
        $missing[] = 'aptitude';
    }
    if (!isset($scores['technical'])) {
        $missing[] = 'technical';
    }

    if (count($missing) > 0) {
        echo json_encode([
            'success' => true,
            'status'  => 'needs_data',
            'missing' => $missing
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 4. Build the six features
    // ---------------------------------------------------------

    $features = [
        'skills_count'         => readiness_count_skills($resume['skills']),
        'projects_count'       => readiness_count_titled_entries($resume['projects']),
        'experience_count'     => readiness_count_titled_entries($resume['experience']),
        'certifications_count' => readiness_count_bullets($resume['certifications']),
        'aptitude_score'       => round($scores['aptitude']['avg'], 2),
        'technical_score'      => round($scores['technical']['avg'], 2)
    ];

    // ---------------------------------------------------------
    // 5. Ask the Flask model service for a prediction
    // ---------------------------------------------------------

    $ch = curl_init(READINESS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($features),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5
    ]);

    $responseBody = curl_exec($ch);
    $curlError    = curl_error($ch);
    $httpStatus   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false || $httpStatus !== 200) {
        error_log('Readiness API call failed. HTTP ' . $httpStatus . ' ' . $curlError);

        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'The readiness prediction service is not available right now.'
        ]);
        exit;
    }

    $prediction = json_decode($responseBody, true);

    if (!is_array($prediction) || empty($prediction['success'])) {
        error_log('Readiness API returned an unexpected response: ' . $responseBody);

        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => 'The readiness prediction service returned an unexpected response.'
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 6. Return prediction + supporting insights
    // ---------------------------------------------------------

    echo json_encode([
        'success'       => true,
        'status'        => 'ready',
        'readiness'     => $prediction['readiness'],
        'probabilities' => $prediction['probabilities'],
        'features'      => $features,
        'breakdown'     => readiness_breakdown($features),
        'attempts'      => [
            'aptitude'  => $scores['aptitude']['attempts'],
            'technical' => $scores['technical']['attempts']
        ],
        'note' => 'Estimated by a model trained on rubric-labelled synthetic data, '
                . 'not on real placement outcomes.'
    ]);

} catch (Exception $e) {
    error_log('Get readiness error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while calculating your readiness.'
    ]);
}