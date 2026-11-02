<?php
/**
 * HireWise - Logout API Endpoint
 * Destroys user session and returns JSON success response.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/auth.php';

logout_user();

http_response_code(200);
echo json_encode([
    'success'  => true,
    'message'  => 'Logged out successfully.',
    'redirect' => 'login.html'
]);
