<?php
/**
 * HireWise - Placement Assessment Module
 * Endpoint: Start Assessment (fetch random question set + create attempt)
 *
 * NOTE: Adjust the require_once paths below to match where you place
 * this file relative to your db.php and auth.php.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

// ---------------------------------------------------------
// 1. Read and validate the incoming configuration
// ---------------------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

$category      = $input['category'] ?? '';
$mode          = $input['mode'] ?? '';
$topic         = $input['topic'] ?? '';
$difficulty    = $input['difficulty'] ?? '';
$questionCount = (int)($input['questionCount'] ?? 0);

$allowedCategories   = ['aptitude', 'technical', 'full'];
$allowedModes        = ['mixed', 'focused'];
$allowedDifficulties = ['easy', 'medium', 'mixed'];
$allowedCounts       = [10, 20, 30];

if (!in_array($category, $allowedCategories, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category.']);
    exit;
}

if (!in_array($mode, $allowedModes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid mode.']);
    exit;
}

if (!in_array($difficulty, $allowedDifficulties, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid difficulty.']);
    exit;
}

if (!in_array($questionCount, $allowedCounts, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid question count.']);
    exit;
}

$pdo  = get_db_connection();
$user = current_user();

// ---------------------------------------------------------
// 2. Helper functions
// ---------------------------------------------------------

/**
 * Resolve the frontend's topic value into a real topic_id,
 * or null when it means "no topic filter"
 * (All Aptitude / All Technical / Full assessment).
 */
function resolve_topic_id(PDO $pdo, string $category, string $topic): ?int {
    $noFilterValues = ['all_aptitude', 'all_technical', 'full'];

    if (in_array($topic, $noFilterValues, true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT topic_id FROM topics
         WHERE topic_code = :code AND category = :cat AND is_active = 1'
    );
    $stmt->execute(['code' => $topic, 'cat' => $category]);
    $row = $stmt->fetch();

    return $row ? (int)$row['topic_id'] : null;
}

/**
 * Fetch a random set of approved, active questions matching the given filters.
 */
function fetch_random_questions(
    PDO $pdo,
    string $category,
    ?int $topicId,
    string $difficulty,
    int $limit
): array {
    if ($limit <= 0) {
        return [];
    }

    $sql = 'SELECT question_id, category, topic_id, difficulty, question_text,
                   option_a, option_b, option_c, option_d
            FROM questions
            WHERE category = :category
              AND is_active = 1
              AND review_status = "approved"';

    $params = ['category' => $category];

    if ($topicId !== null) {
        $sql .= ' AND topic_id = :topic_id';
        $params['topic_id'] = $topicId;
    }

    if ($difficulty !== 'mixed') {
        $sql .= ' AND difficulty = :difficulty';
        $params['difficulty'] = $difficulty;
    }

    // LIMIT can't be bound as a normal PDO param reliably across drivers,
    // and it's already a validated integer, so it's safe to inline here.
    $sql .= ' ORDER BY RAND() LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

// ---------------------------------------------------------
// 3. Select questions according to category
// ---------------------------------------------------------

try {
    $questions = [];

    if ($category === 'full') {
        // Split requested count across aptitude and technical.
        $aptitudeCount  = intdiv($questionCount, 2);
        $technicalCount = $questionCount - $aptitudeCount;

        $questions = array_merge(
            fetch_random_questions($pdo, 'aptitude', null, $difficulty, $aptitudeCount),
            fetch_random_questions($pdo, 'technical', null, $difficulty, $technicalCount)
        );

        shuffle($questions);

        $topicIdForAttempt = null;

    } else {
        $topicIdForAttempt = resolve_topic_id($pdo, $category, $topic);
        $questions = fetch_random_questions(
            $pdo,
            $category,
            $topicIdForAttempt,
            $difficulty,
            $questionCount
        );
    }

    // ---------------------------------------------------------
    // 4. Make sure we actually found enough questions
    // ---------------------------------------------------------

    if (count($questions) < $questionCount) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Not enough approved questions are available for this '
                . 'configuration yet (' . count($questions) . ' found, '
                . $questionCount . ' requested). Try a different setup, a lower '
                . 'question count, or add more questions to the bank.'
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 5. Create the attempt + lock in the question set
    // ---------------------------------------------------------

    $pdo->beginTransaction();

    $insertAttempt = $pdo->prepare(
        'INSERT INTO assessment_attempts
            (user_id, category, topic_id, mode, difficulty, total_questions, status)
         VALUES
            (:user_id, :category, :topic_id, :mode, :difficulty, :total_questions, "in_progress")'
    );
    $insertAttempt->execute([
        'user_id'         => $user['user_id'],
        'category'        => $category,
        'topic_id'        => $topicIdForAttempt,
        'mode'            => $mode,
        'difficulty'      => $difficulty,
        'total_questions' => $questionCount
    ]);

    $attemptId = (int)$pdo->lastInsertId();

    $insertAnswer = $pdo->prepare(
        'INSERT INTO attempt_answers (attempt_id, question_id, question_order)
         VALUES (:attempt_id, :question_id, :question_order)'
    );

    $order = 1;
    $responseQuestions = [];

    foreach ($questions as $q) {
        $insertAnswer->execute([
            'attempt_id'     => $attemptId,
            'question_id'    => $q['question_id'],
            'question_order' => $order
        ]);

        // IMPORTANT: correct_option and explanation are never sent here.
        $responseQuestions[] = [
            'question_order' => $order,
            'question_id'    => (int)$q['question_id'],
            'question_text'  => $q['question_text'],
            'options' => [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d']
            ]
        ];

        $order++;
    }

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'attempt_id' => $attemptId,
        'questions'  => $responseQuestions
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Start assessment error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while starting the assessment.'
    ]);
}