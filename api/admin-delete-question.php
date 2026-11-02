<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: "Delete" a question.
//
// This is a SOFT delete (is_active = 0), not a real DELETE FROM.
// A hard delete would break attempt_answers rows that reference
// this question_id for any student who has already answered it -
// their past results would error out or lose data on lookup.
// Setting is_active = 0 removes it from future assessments
// (start-assessment.php already filters WHERE is_active = 1)
// while keeping historical results intact.
//
// Place at: hirewise/api/admin-delete-question.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['question_id']) || !is_numeric($input['question_id'])) {
    send_json(['success' => false, 'message' => 'A valid question_id is required.'], 400);
}

$questionId = (int) $input['question_id'];

// Allows the same endpoint to reactivate too (used by the "Restore"
// action on an already-inactive question), rather than needing a
// separate file just to flip the flag back.
$targetActive = isset($input['is_active']) ? (int) (bool) $input['is_active'] : 0;

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('UPDATE questions SET is_active = :is_active WHERE question_id = :id');
    $stmt->execute([':is_active' => $targetActive, ':id' => $questionId]);

    $checkStmt = $pdo->prepare('SELECT question_id FROM questions WHERE question_id = :id');
    $checkStmt->execute([':id' => $questionId]);
    if (!$checkStmt->fetch()) {
        send_json(['success' => false, 'message' => 'Question not found.'], 404);
    }

    send_json(['success' => true, 'question_id' => $questionId, 'is_active' => $targetActive]);

} catch (PDOException $e) {
    error_log('Admin delete question error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to update this question.'], 500);
}