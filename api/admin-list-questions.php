<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: List questions, with optional filters.
//
// Shared across both aptitude and technical - category is just a
// filter parameter here, same as the rest of the assessment system
// already treats it (see start-assessment.php).
//
// Query params (all optional):
//   category    = aptitude | technical
//   topic_code  = e.g. 'quantitative', 'dsa', ...
//   difficulty  = easy | medium | hard
//   status      = active | inactive | all   (default: all)
//
// Place at: hirewise/api/admin-list-questions.php
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

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$category   = $_GET['category'] ?? '';
$topicCode  = $_GET['topic_code'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';
$status     = $_GET['status'] ?? 'all';

$allowedCategories   = ['aptitude', 'technical'];
$allowedDifficulties = ['easy', 'medium', 'hard'];
$allowedStatuses     = ['active', 'inactive', 'all'];

if ($category !== '' && !in_array($category, $allowedCategories, true)) {
    send_json(['success' => false, 'message' => 'Invalid category filter.'], 400);
}
if ($difficulty !== '' && !in_array($difficulty, $allowedDifficulties, true)) {
    send_json(['success' => false, 'message' => 'Invalid difficulty filter.'], 400);
}
if (!in_array($status, $allowedStatuses, true)) {
    send_json(['success' => false, 'message' => 'Invalid status filter.'], 400);
}

try {
    $pdo = get_db_connection();

    $sql = '
        SELECT
            q.question_id, q.category, q.topic_id, t.topic_code,
            q.difficulty, q.question_text,
            q.option_a, q.option_b, q.option_c, q.option_d,
            q.correct_option, q.explanation, q.is_active,
            q.review_status, q.created_at
        FROM questions q
        LEFT JOIN topics t ON t.topic_id = q.topic_id
        WHERE 1 = 1
    ';
    $params = [];

    if ($category !== '') {
        $sql .= ' AND q.category = :category';
        $params[':category'] = $category;
    }
    if ($topicCode !== '') {
        $sql .= ' AND t.topic_code = :topic_code';
        $params[':topic_code'] = $topicCode;
    }
    if ($difficulty !== '') {
        $sql .= ' AND q.difficulty = :difficulty';
        $params[':difficulty'] = $difficulty;
    }
    if ($status === 'active') {
        $sql .= ' AND q.is_active = 1';
    } elseif ($status === 'inactive') {
        $sql .= ' AND q.is_active = 0';
    }
    // status === 'all' -> no filter on is_active

    $sql .= ' ORDER BY q.created_at DESC, q.question_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$q) {
        $q['question_id'] = (int) $q['question_id'];
        $q['topic_id']    = $q['topic_id'] !== null ? (int) $q['topic_id'] : null;
        $q['is_active']   = (int) $q['is_active'];
    }
    unset($q);

    send_json(['success' => true, 'questions' => $questions, 'count' => count($questions)]);

} catch (PDOException $e) {
    error_log('Admin list questions error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to fetch questions.'], 500);
}