<?php
/**
 * HireWise - Module 3 (Placement Readiness)
 * Feature helper functions.
 *
 * Turns the text stored by the Resume Intelligence module into
 * numbers the readiness model can use. This file only READS and
 * COUNTS - it never changes anything in the resumes table.
 *
 * Place at: hirewise/includes/readiness_features.php
 */

// Rubric caps - these MUST match generate_synthetic_dataset.py.
const READINESS_SKILLS_CAP         = 15;
const READINESS_PROJECTS_CAP       = 3;
const READINESS_EXPERIENCE_CAP     = 2;
const READINESS_CERTIFICATIONS_CAP = 6;

/**
 * Splits stored text into trimmed, non-empty lines.
 */
function readiness_split_lines(?string $text): array {
    if ($text === null) {
        return [];
    }

    $lines = preg_split('/\R/u', $text);
    $lines = array_map('trim', $lines);

    return array_values(array_filter($lines, fn($line) => $line !== ''));
}

/**
 * True when a line starts with a bullet character.
 */
function readiness_is_bullet(string $line): bool {
    return (bool)preg_match('/^[•·∗*\-]\s*/u', $line);
}

/**
 * Number of distinct skills in the stored JSON array.
 * Case-insensitive, so "Python" and "python" count once.
 */
function readiness_count_skills(?string $skillsJson): int {
    if ($skillsJson === null || trim($skillsJson) === '') {
        return 0;
    }

    $decoded = json_decode($skillsJson, true);

    if (!is_array($decoded)) {
        return 0;
    }

    $unique = [];

    foreach ($decoded as $skill) {
        if (is_string($skill) && trim($skill) !== '') {
            $unique[mb_strtolower(trim($skill))] = true;
        }
    }

    return count($unique);
}

/**
 * Counts entries in sections written as:
 *     Title | details
 *     • bullet
 *     wrapped bullet text
 * (used for Experience and Projects).
 *
 * Only non-bullet lines containing a "|" are counted as entry titles.
 * Bullet text often wraps onto a second line with no bullet of its own,
 * so "any non-bullet line" would overcount.
 *
 * KNOWN LIMITATION: a title written without a "|" is not recognised.
 * If the section has text but no "|" titles, it counts as 1 entry.
 */
function readiness_count_titled_entries(?string $text): int {
    $lines = readiness_split_lines($text);

    if (count($lines) === 0) {
        return 0;
    }

    $titles = 0;

    foreach ($lines as $line) {
        if (!readiness_is_bullet($line) && strpos($line, '|') !== false) {
            $titles++;
        }
    }

    return $titles > 0 ? $titles : 1;
}

/**
 * Counts bullet lines (used for Certifications, where each bullet is
 * one complete entry).
 */
function readiness_count_bullets(?string $text): int {
    $count = 0;

    foreach (readiness_split_lines($text) as $line) {
        if (readiness_is_bullet($line)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Converts each raw feature into a 0-100 sub-score using the rubric caps.
 * Used for the "supporting insights" on the readiness page - NOT fed to
 * the model (the model receives raw features only).
 */
function readiness_breakdown(array $features): array {
    $scale = fn($value, $cap) => round(min($value / $cap, 1) * 100, 1);

    return [
        'skills'         => $scale($features['skills_count'], READINESS_SKILLS_CAP),
        'projects'       => $scale($features['projects_count'], READINESS_PROJECTS_CAP),
        'experience'     => $scale($features['experience_count'], READINESS_EXPERIENCE_CAP),
        'certifications' => $scale($features['certifications_count'], READINESS_CERTIFICATIONS_CAP),
        'aptitude'       => round($features['aptitude_score'], 1),
        'technical'      => round($features['technical_score'], 1)
    ];
}