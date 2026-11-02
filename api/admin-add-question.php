<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: Add a new question (aptitude or technical - same
// table, category is just a field, matching how the rest of the
// assessment system already treats it).
//
// Place at: hirewise/api/admin-add-question.php
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

if (!is_array($input)) {
    send_json(['success' => false, 'message' => 'Invalid request body.'], 400);
}

// Trim all string fields up front so validation and storage agree
// on what counts as "empty".
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

    $topicId = admin_resolve_topic_id($pdo, $data['category'], $data['topic_code']);
    if ($topicId === null) {
        send_json([
            'success' => false,
            'message' => "'{$data['topic_code']}' is not a valid topic for the '{$data['category']}' category."
        ], 400);
    }

    $hash = compute_question_hash($data['category'], $data['topic_code'], $data['difficulty'], $data['question_text']);

    $stmt = $pdo->prepare('
        INSERT INTO questions (
            category, topic_id, difficulty, question_text,
            option_a, option_b, option_c, option_d, correct_option,
            explanation, source_type, source_reference, review_status,
            is_active, content_hash
        ) VALUES (
            :category, :topic_id, :difficulty, :question_text,
            :option_a, :option_b, :option_c, :option_d, :correct_option,
            :explanation, :source_type, :source_reference, :review_status,
            1, :content_hash
        )
    ');

    $stmt->execute([
        ':category'        => $data['category'],
        ':topic_id'        => $topicId,
        ':difficulty'      => $data['difficulty'],
        ':question_text'   => $data['question_text'],
        ':option_a'        => $data['option_a'],
        ':option_b'        => $data['option_b'],
        ':option_c'        => $data['option_c'],
        ':option_d'        => $data['option_d'],
        ':correct_option'  => $data['correct_option'],
        ':explanation'     => $data['explanation'],
        ':source_type'     => 'manual',
        ':source_reference'=> 'Added via Admin Panel',
        ':review_status'   => 'approved',
        ':content_hash'    => $hash
    ]);

    $questionId = (int) $pdo->lastInsertId();

    send_json(['success' => true, 'question_id' => $questionId]);

} catch (PDOException $e) {
    // SQLSTATE 23000 = integrity constraint violation, which is
    // what fires if content_hash hits its unique index - meaning
    // this exact question (same category/topic/difficulty/text)
    // already exists in the bank.
    if ($e->getCode() === '23000') {
        send_json([
            'success' => false,
            'message' => 'An identical question already exists in the question bank.'
        ], 409);
    }

    error_log('Admin add question error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to add this question.'], 500);
}