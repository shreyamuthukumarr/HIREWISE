<?php
/**
 * HireWise - Registration API Endpoint
 * Handles student registration with validation and password hashing.
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

// Parse input payload (supports both JSON body and FormData)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$name            = trim($data['name'] ?? '');
$email           = trim($data['email'] ?? '');
$password        = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? null;

// Validation rules
$errors = [];

if (empty($name)) {
    $errors[] = 'Full name is required.';
} elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Full name must be between 2 and 100 characters.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long.';
} elseif (strlen($password) > 72) {
    $errors[] = 'Password cannot exceed 72 characters.';
}

if ($confirmPassword !== null && $password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = get_db_connection();

    // Check for duplicate email
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
        exit;
    }

    // Hash password with bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert user into database
    $insertStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $insertStmt->execute([$name, $email, $passwordHash, 'student']);

    $newUserId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! You can now log in to your account.',
        'user_id' => $newUserId
    ]);
} catch (PDOException $e) {
    error_log('Registration Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to complete registration due to a server error. Please try again later.']);
}
