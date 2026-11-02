<?php

// ============================================================
// HireWise - Offer Comparison
// Endpoint: Delete one of the logged-in user's own offers.
//
// Place at: hirewise/api/delete_offer.php
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

require_login();

$user = current_user();

if (!is_array($user) || !isset($user['user_id']) || !is_numeric($user['user_id'])) {
    send_json(['success' => false, 'message' => 'Unable to identify the logged-in user.'], 401);
}

$userId = (int) $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// ------------------------------------------------------------
// Read JSON body (this endpoint takes JSON, not form-data, since
// there is no file involved - matches how js/offers.js calls it).
// ------------------------------------------------------------

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!is_array($body) || !isset($body['offer_id']) || !is_numeric($body['offer_id'])) {
    send_json(['success' => false, 'message' => 'A valid offer_id is required.'], 400);
}

$offerId = (int) $body['offer_id'];

try {
    $pdo = get_db_connection();

    // WHERE includes user_id, not just offer_id - this is the
    // ownership check. Without it, any logged-in user could delete
    // another user's offer just by guessing/incrementing IDs.
    $stmt = $pdo->prepare("
        DELETE FROM offers
        WHERE offer_id = :offer_id AND user_id = :user_id
    ");

    $stmt->execute([
        ':offer_id' => $offerId,
        ':user_id'  => $userId
    ]);

    if ($stmt->rowCount() === 0) {
        // Either the offer doesn't exist, or it belongs to someone
        // else - the response is deliberately the same either way,
        // so this endpoint never confirms/denies another user's
        // offer IDs exist.
        send_json(['success' => false, 'message' => 'Offer not found.'], 404);
    }

    send_json(['success' => true, 'offer_id' => $offerId]);

} catch (PDOException $e) {
    error_log('Delete offer error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to remove this offer.'], 500);
}