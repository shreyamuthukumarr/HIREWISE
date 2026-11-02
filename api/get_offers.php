<?php

// ============================================================
// HireWise - Offer Comparison
// Endpoint: List the logged-in user's saved offers.
//
// Place at: hirewise/api/get_offers.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT
            offer_id, company_name, job_role, salary_details, location,
            work_mode, benefits, bond_details, notice_period,
            other_conditions, source_type, created_at
        FROM offers
        WHERE user_id = :user_id
        ORDER BY created_at ASC, offer_id ASC
    ");

    $stmt->execute([':user_id' => $userId]);

    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast offer_id to int for every row (PDO returns strings by
    // default), so the frontend's offer.offer_id comparisons work
    // predictably.
    foreach ($offers as &$offer) {
        $offer['offer_id'] = (int) $offer['offer_id'];
    }
    unset($offer);

    send_json([
        'success' => true,
        'offers'  => $offers
    ]);

} catch (PDOException $e) {
    error_log('Get offers error: ' . $e->getMessage());
    send_json(['success' => false, 'message' => 'Unable to fetch your offers.'], 500);
}