<?php
/**
 * HireWise - Session Status API Endpoint
 * Returns current authentication state and user details if logged in.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/auth.php';

$user = current_user();

if ($user !== null) {
    http_response_code(200);
    echo json_encode([
        'authenticated' => true,
        'user'          => $user
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'authenticated' => false,
        'user'          => null
    ]);
}
