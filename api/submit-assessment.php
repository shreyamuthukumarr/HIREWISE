<?php
/**
 * HireWise - Placement Assessment Module
 * Endpoint: Submit Assessment (score answers, finalize attempt)
 *
 * NOTE: Adjust the require_once paths below to match your folder structure
 * (same adjustment as start-assessment.php).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

// ---------------------------------------------------------
// 1. Read and validate the incoming payload
// ---------------------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

$attemptId = (int)($input['attempt_id'] ?? 0);
$answers   = $input['answers'] ?? null;

if ($attemptId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid attempt ID.']);
    exit;
}

if (!is_array($answers) || count($answers) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No answers were submitted.']);
    exit;
}

// Normalize submitted answers into [question_id => selected_option|null],
// discarding anything malformed rather than trusting it as-is.
$allowedOptions = ['A', 'B', 'C', 'D'];
$submittedAnswers = [];

foreach ($answers as $answer) {
    if (!is_array($answer) || !isset($answer['question_id'])) {
        continue;
    }

    $qid = (int)$answer['question_id'];
    $selected = $answer['selected_option'] ?? null;

    if ($selected !== null && !in_array($selected, $allowedOptions, true)) {
        $selected = null;
    }

    $submittedAnswers[$qid] = $selected;
}

$pdo  = get_db_connection();
$user = current_user();

try {
    $pdo->beginTransaction();

    // ---------------------------------------------------------
    // 2. Lock the attempt row (this is what blocks a double submit)
    // ---------------------------------------------------------

    $attemptStmt = $pdo->prepare(
        'SELECT attempt_id, status, total_questions
         FROM assessment_attempts
         WHERE attempt_id = :attempt_id AND user_id = :user_id
         FOR UPDATE'
    );
    $attemptStmt->execute([
        'attempt_id' => $attemptId,
        'user_id'    => $user['user_id']
    ]);
    $attempt = $attemptStmt->fetch();

    if (!$attempt) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Assessment attempt not found.'
        ]);
        exit;
    }

    if ($attempt['status'] !== 'in_progress') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This assessment has already been submitted.'
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 3. Confirm the submitted questions match what was locked in
    //    for this attempt back at start-assessment.php
    // ---------------------------------------------------------

    $expectedStmt = $pdo->prepare(
        'SELECT aa.question_id, q.correct_option
         FROM attempt_answers aa
         JOIN questions q ON q.question_id = aa.question_id
         WHERE aa.attempt_id = :attempt_id'
    );
    $expectedStmt->execute(['attempt_id' => $attemptId]);
    $expectedRows = $expectedStmt->fetchAll();

    if (count($expectedRows) === 0) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No questions are associated with this attempt.'
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 4. Score each question and update attempt_answers
    // ---------------------------------------------------------

    $updateAnswer = $pdo->prepare(
        'UPDATE attempt_answers
         SET selected_option = :selected_option, is_correct = :is_correct
         WHERE attempt_id = :attempt_id AND question_id = :question_id'
    );

    $correctCount = 0;

    foreach ($expectedRows as $row) {
        $questionId = (int)$row['question_id'];
        $correctOption = $row['correct_option'];

        // Anything not explicitly submitted counts as unanswered.
        $selectedOption = $submittedAnswers[$questionId] ?? null;

        $isCorrect = ($selectedOption !== null && $selectedOption === $correctOption) ? 1 : 0;
        $correctCount += $isCorrect;

        $updateAnswer->execute([
            'selected_option' => $selectedOption,
            'is_correct'      => $isCorrect,
            'attempt_id'      => $attemptId,
            'question_id'     => $questionId
        ]);
    }

    // ---------------------------------------------------------
    // 5. Finalize the attempt
    // ---------------------------------------------------------

    $totalQuestions = (int)$attempt['total_questions'];
    $scorePercentage = round(($correctCount / $totalQuestions) * 100, 2);

    $updateAttempt = $pdo->prepare(
        'UPDATE assessment_attempts
         SET correct_count = :correct_count,
             score_percentage = :score_percentage,
             status = "completed",
             completed_at = NOW()
         WHERE attempt_id = :attempt_id'
    );
    $updateAttempt->execute([
        'correct_count'    => $correctCount,
        'score_percentage' => $scorePercentage,
        'attempt_id'       => $attemptId
    ]);

    $pdo->commit();

    echo json_encode([
        'success'          => true,
        'attempt_id'       => $attemptId,
        'correct_count'    => $correctCount,
        'total_questions'  => $totalQuestions,
        'score_percentage' => $scorePercentage
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Submit assessment error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while submitting your assessment.'
    ]);
}