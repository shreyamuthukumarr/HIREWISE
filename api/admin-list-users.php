<?php

// ============================================================
// HireWise - Admin Module
// Endpoint: List all registered users.
//
// Protected by require_admin() - a student hitting this URL
// directly gets a 403 JSON response, never the actual data.
//
// Place at: hirewise/api/admin-list-users.php
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

// require_admin() internally calls require_login() first, then
// checks the 'admin' role - exactly the existing function, no new
// authentication logic written here.
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->query('
        SELECT user_id, name, email, role, is_active, created_at
        FROM users
        ORDER BY created_at DESC
    ');

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['user_id']   = (int) $u['user_id'];
        $u['is_active'] = (int) $u['is_active'];
    }
    unset($u);

    send_json(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    error_log('Admin list users error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to fetch users.'], 500);
}