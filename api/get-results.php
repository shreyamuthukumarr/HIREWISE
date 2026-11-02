<?php
/**
 * HireWise - Placement Assessment Module
 * Endpoint: Get Results (attempt summary + question-by-question breakdown)
 *
 * NOTE: Adjust the require_once paths below to match your folder structure
 * (same adjustment as start-assessment.php / submit-assessment.php).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

// ---------------------------------------------------------
// 1. Validate the attempt_id query parameter
// ---------------------------------------------------------

$attemptId = (int)($_GET['attempt_id'] ?? 0);

if ($attemptId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid attempt ID.']);
    exit;
}

$pdo  = get_db_connection();
$user = current_user();

try {
    // ---------------------------------------------------------
    // 2. Fetch the attempt, scoped to the logged-in user
    //    (this is the ownership check)
    // ---------------------------------------------------------

    $attemptStmt = $pdo->prepare(
        'SELECT aa.attempt_id, aa.category, aa.topic_id, aa.mode, aa.difficulty,
                aa.total_questions, aa.correct_count, aa.score_percentage,
                aa.status, aa.completed_at, t.topic_name
         FROM assessment_attempts aa
         LEFT JOIN topics t ON t.topic_id = aa.topic_id
         WHERE aa.attempt_id = :attempt_id AND aa.user_id = :user_id'
    );
    $attemptStmt->execute([
        'attempt_id' => $attemptId,
        'user_id'    => $user['user_id']
    ]);
    $attempt = $attemptStmt->fetch();

    if (!$attempt) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Assessment result not found.'
        ]);
        exit;
    }

    if ($attempt['status'] !== 'completed') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This assessment has not been completed yet.'
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 3. Fetch the question-by-question breakdown
    // ---------------------------------------------------------

    $answersStmt = $pdo->prepare(
        'SELECT ans.question_order, ans.selected_option, ans.is_correct,
                q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                q.correct_option, q.explanation
         FROM attempt_answers ans
         JOIN questions q ON q.question_id = ans.question_id
         WHERE ans.attempt_id = :attempt_id
         ORDER BY ans.question_order'
    );
    $answersStmt->execute(['attempt_id' => $attemptId]);
    $rows = $answersStmt->fetchAll();

    $questions = array_map(function ($row) {
        return [
            'question_order'  => (int)$row['question_order'],
            'question_text'   => $row['question_text'],
            'options' => [
                'A' => $row['option_a'],
                'B' => $row['option_b'],
                'C' => $row['option_c'],
                'D' => $row['option_d']
            ],
            'selected_option' => $row['selected_option'],
            'correct_option'  => $row['correct_option'],
            'is_correct'      => (bool)$row['is_correct'],
            'explanation'     => $row['explanation']
        ];
    }, $rows);

    // ---------------------------------------------------------
    // 4. Work out a readable topic label
    // ---------------------------------------------------------

    if ($attempt['category'] === 'full') {
        $topicLabel = 'Full Assessment (Aptitude + Technical)';
    } elseif ($attempt['topic_name']) {
        $topicLabel = $attempt['topic_name'];
    } elseif ($attempt['category'] === 'aptitude') {
        $topicLabel = 'All Aptitude Topics';
    } else {
        $topicLabel = 'All Technical Topics';
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'attempt_id'       => (int)$attempt['attempt_id'],
            'category'         => $attempt['category'],
            'topic_label'      => $topicLabel,
            'difficulty'       => $attempt['difficulty'],
            'total_questions'  => (int)$attempt['total_questions'],
            'correct_count'    => (int)$attempt['correct_count'],
            'score_percentage' => (float)$attempt['score_percentage'],
            'completed_at'     => $attempt['completed_at']
        ],
        'questions' => $questions
    ]);

} catch (Exception $e) {
    error_log('Get results error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while loading your results.'
    ]);
}