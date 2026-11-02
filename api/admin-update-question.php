<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: Edit an existing question.
//
// Uses full-replace semantics: the edit form always sends every
// field (pre-filled with current values), so this mirrors
// admin-add-question.php's validation exactly rather than
// implementing separate partial-patch logic.
//
// Place at: hirewise/api/admin-update-question.php
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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/question_validation.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['question_id']) || !is_numeric($input['question_id'])) {
    send_json(['success' => false, 'message' => 'A valid question_id is required.'], 400);
}

$questionId = (int) $input['question_id'];

$fields = ['category', 'topic_code', 'difficulty', 'question_text',
           'option_a', 'option_b', 'option_c', 'option_d',
           'correct_option', 'explanation'];

$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($input[$f]) ? trim((string) $input[$f]) : '';
}

$validationError = validate_question_fields($data);
if ($validationError !== null) {
    send_json(['success' => false, 'message' => $validationError], 400);
}

try {
    $pdo = get_db_connection();

    // Confirm the question actually exists before trying to update it.
    $existsStmt = $pdo->prepare('SELECT question_id FROM questions WHERE question_id = :id');
    $existsStmt->execute([':id' => $questionId]);
    if (!$existsStmt->fetch()) {
        send_json(['success' => false, 'message' => 'Question not found.'], 404);
    }

    $topicId = admin_resolve_topic_id($pdo, $data['category'], $data['topic_code']);
    if ($topicId === null) {
        send_json([
            'success' => false,
            'message' => "'{$data['topic_code']}' is not a valid topic for the '{$data['category']}' category."
        ], 400);
    }

    $hash = compute_question_hash($data['category'], $data['topic_code'], $data['difficulty'], $data['question_text']);

    $stmt = $pdo->prepare('
        UPDATE questions SET
            category = :category,
            topic_id = :topic_id,
            difficulty = :difficulty,
            question_text = :question_text,
            option_a = :option_a,
            option_b = :option_b,
            option_c = :option_c,
            option_d = :option_d,
            correct_option = :correct_option,
            explanation = :explanation,
            content_hash = :content_hash
        WHERE question_id = :question_id
    ');

    $stmt->execute([
        ':category'       => $data['category'],
        ':topic_id'       => $topicId,
        ':difficulty'     => $data['difficulty'],
        ':question_text'  => $data['question_text'],
        ':option_a'       => $data['option_a'],
        ':option_b'       => $data['option_b'],
        ':option_c'       => $data['option_c'],
        ':option_d'       => $data['option_d'],
        ':correct_option' => $data['correct_option'],
        ':explanation'    => $data['explanation'],
        ':content_hash'   => $hash,
        ':question_id'    => $questionId
    ]);

    send_json(['success' => true, 'question_id' => $questionId]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        send_json([
            'success' => false,
            'message' => 'Another question with identical content already exists in the bank.'
        ], 409);
    }

    error_log('Admin update question error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to update this question.'], 500);
}