<?php

// ============================================================
// HireWise - Admin Module
// Shared helpers for validating and hashing question data.
// Used by api/admin-add-question.php and api/admin-update-question.php
// so the same rules apply in both places, written once.
//
// Place at: hirewise/includes/question_validation.php
// ============================================================

const QUESTION_ALLOWED_CATEGORIES   = ['aptitude', 'technical'];
const QUESTION_ALLOWED_DIFFICULTIES = ['easy', 'medium', 'hard'];
const QUESTION_ALLOWED_OPTIONS      = ['A', 'B', 'C', 'D'];

/**
 * Resolves a topic_code + category into a real topic_id.
 * Same lookup pattern as start-assessment.php's resolve_topic_id(),
 * but admin always supplies a specific topic - no "no filter" values.
 */
function admin_resolve_topic_id(PDO $pdo, string $category, string $topicCode): ?int {
    $stmt = $pdo->prepare(
        'SELECT topic_id FROM topics
         WHERE topic_code = :code AND category = :cat AND is_active = 1'
    );
    $stmt->execute(['code' => $topicCode, 'cat' => $category]);
    $row = $stmt->fetch();

    return $row ? (int) $row['topic_id'] : null;
}

/**
 * Same hash formula used by the original aptitude/technical SQL
 * batches: sha256(category|topic_code|difficulty|question_text).
 * Keeping the formula identical means admin-added questions sit in
 * the same uniqueness space as the bulk-imported ones - a question
 * added here that's an exact duplicate of an existing one will
 * collide on content_hash, which is caught and reported clearly
 * rather than silently creating a duplicate.
 */
function compute_question_hash(string $category, string $topicCode, string $difficulty, string $questionText): string {
    return hash('sha256', $category . '|' . $topicCode . '|' . $difficulty . '|' . $questionText);
}

/**
 * Validates question field values (not including topic resolution,
 * which needs a DB call and is done separately by the caller).
 *
 * @return string|null  An error message, or null if everything is valid.
 */
function validate_question_fields(array $data): ?string {
    if (!in_array($data['category'] ?? '', QUESTION_ALLOWED_CATEGORIES, true)) {
        return "Category must be 'aptitude' or 'technical'.";
    }

    if (!in_array($data['difficulty'] ?? '', QUESTION_ALLOWED_DIFFICULTIES, true)) {
        return "Difficulty must be 'easy', 'medium', or 'hard'.";
    }

    if (!in_array($data['correct_option'] ?? '', QUESTION_ALLOWED_OPTIONS, true)) {
        return "Correct option must be 'A', 'B', 'C', or 'D'.";
    }

    $requiredTextFields = [
        'topic_code'    => 'Topic',
        'question_text' => 'Question text',
        'option_a'      => 'Option A',
        'option_b'      => 'Option B',
        'option_c'      => 'Option C',
        'option_d'      => 'Option D',
        'explanation'   => 'Explanation'
    ];

    foreach ($requiredTextFields as $key => $label) {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return "$label cannot be empty.";
        }
    }

    if (mb_strlen($data['question_text']) > 1000) {
        return 'Question text is too long (maximum 1000 characters).';
    }

    return null; // no errors
}