<?php
/**
 * HireWise - Login API Endpoint
 * Authenticates users and initiates a secure session.
 *
 * CHANGES FOR ADMIN MODULE (both additive, nothing removed):
 *   1. A deactivated account (is_active = 0) is blocked from logging
 *      in, with a clear message - checked AFTER password verification
 *      so we never reveal whether an email exists before confirming
 *      the password is even correct.
 *   2. The redirect target now depends on role: admins go to
 *      admin.php, everyone else goes to dashboard.html exactly as
 *      before.
 */

header('Content-Type: application/json; charset=UTF-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Parse input payload (JSON body or FormData)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide both email and password.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    $pdo = get_db_connection();

    // Query user record by email (now also selecting is_active)
    $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify user exists and password hash matches - unchanged
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email address or password.']);
        exit;
    }

    // NEW: block deactivated accounts. Checked only after the
    // password has already been confirmed correct, so a deactivated
    // account doesn't leak information to someone who doesn't know
    // the password.
    if ((int)$user['is_active'] === 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'This account has been deactivated. Please contact an administrator.'
        ]);
        exit;
    }

    // Initiate authenticated session - unchanged
    login_user($user);

    // NEW: role-based redirect. Students see exactly the same
    // 'dashboard.html' as before; only admins are routed differently.
    $redirectTarget = ($user['role'] === 'admin') ? 'admin.php' : 'dashboard.html';

    http_response_code(200);
    echo json_encode([
        'success'  => true,
        'message'  => 'Login successful!',
        'redirect' => $redirectTarget,
        'user'     => [
            'user_id' => (int)$user['user_id'],
            'name'    => $user['name'],
            'email'   => $user['email'],
            'role'    => $user['role']
        ]
    ]);
} catch (PDOException $e) {
    error_log('Login Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal error occurred during login. Please try again later.']);
}