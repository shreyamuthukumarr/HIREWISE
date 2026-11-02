<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: Update a user's role and/or active status.
//
// One shared endpoint for both actions (role change, activate/
// deactivate) rather than two separate files, since they're the
// same operation shape: update one or more columns on one row.
//
// Place at: hirewise/api/admin-update-user.php
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

if (!is_array($input) || !isset($input['user_id']) || !is_numeric($input['user_id'])) {
    send_json(['success' => false, 'message' => 'A valid user_id is required.'], 400);
}

$targetUserId = (int) $input['user_id'];
$currentAdmin = current_user();

// --------------------------------------------------------------
// Safety guard: an admin cannot change their own role or active
// status through this panel. Without this, an admin could
// accidentally lock themselves out (deactivate self) or demote
// themselves to student with no other admin able to fix it.
// --------------------------------------------------------------

if ($targetUserId === (int) $currentAdmin['user_id']) {
    send_json([
        'success' => false,
        'message' => 'You cannot change your own role or status from this panel.'
    ], 400);
}

// --------------------------------------------------------------
// Figure out which fields were actually sent, validate each one
// that was provided. At least one of role / is_active is required.
// --------------------------------------------------------------

$setClauses = [];
$params = [':user_id' => $targetUserId];

if (array_key_exists('role', $input)) {
    $role = $input['role'];
    if (!in_array($role, ['student', 'admin'], true)) {
        send_json(['success' => false, 'message' => "Role must be 'student' or 'admin'."], 400);
    }
    $setClauses[] = 'role = :role';
    $params[':role'] = $role;
}

if (array_key_exists('is_active', $input)) {
    $isActive = $input['is_active'];
    if (!in_array($isActive, [0, 1, true, false], true)) {
        send_json(['success' => false, 'message' => 'is_active must be 0 or 1.'], 400);
    }
    $setClauses[] = 'is_active = :is_active';
    $params[':is_active'] = $isActive ? 1 : 0;
}

if (count($setClauses) === 0) {
    send_json(['success' => false, 'message' => 'No fields to update were provided.'], 400);
}

try {
    $pdo = get_db_connection();

    $sql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE user_id = :user_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // Either the user doesn't exist, or the values submitted
        // were identical to what was already stored (no-op update).
        // Confirm which, so the frontend doesn't show a false error.
        $check = $pdo->prepare('SELECT user_id FROM users WHERE user_id = :user_id');
        $check->execute([':user_id' => $targetUserId]);
        if (!$check->fetch()) {
            send_json(['success' => false, 'message' => 'User not found.'], 404);
        }
        // User exists, values just didn't change - treat as success.
    }

    // Return the fresh row so the frontend can update in place
    // without needing a full reload.
    $fetch = $pdo->prepare('SELECT user_id, name, email, role, is_active, created_at FROM users WHERE user_id = :user_id');
    $fetch->execute([':user_id' => $targetUserId]);
    $updated = $fetch->fetch(PDO::FETCH_ASSOC);

    $updated['user_id'] = (int) $updated['user_id'];
    $updated['is_active'] = (int) $updated['is_active'];

    send_json(['success' => true, 'user' => $updated]);

} catch (PDOException $e) {
    error_log('Admin update user error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to update this user.'], 500);
}