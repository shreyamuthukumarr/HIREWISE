
<?php
/**
 * HireWise - Admin Account Creation Script
 * Development CLI Only
 *
 * Usage:
 *   php scripts/create_admin.php "Admin Name" admin@example.com "YourPassword"
 */

// Allow execution only through the command line
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: This script can only be executed through the command line.\n");
}

// Require all three arguments
if ($argc < 4) {
    echo "Usage:\n";
    echo 'php scripts/create_admin.php "Admin Name" admin@example.com "YourPassword"' . "\n";
    exit(1);
}

$name     = trim($argv[1]);
$email    = trim($argv[2]);
$password = $argv[3];

// Validate input
if ($name === '') {
    exit("Error: Name cannot be empty.\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Error: Invalid email address.\n");
}

if (strlen($password) < 6) {
    exit("Error: Password must be at least 6 characters long.\n");
}

// Load database connection
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = get_db_connection();

    // Check whether the user already exists
    $stmt = $pdo->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Hash the supplied password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    if ($passwordHash === false) {
        exit("Error: Password hashing failed.\n");
    }

    if ($existing) {
        // Update existing user and grant admin privileges
        $update = $pdo->prepare(
            'UPDATE users
             SET name = ?, password_hash = ?, role = "admin"
             WHERE user_id = ?'
        );

        $update->execute([
            $name,
            $passwordHash,
            $existing['user_id']
        ]);

        echo "Success: Existing user updated with admin privileges.\n";
        echo "User ID: " . $existing['user_id'] . "\n";

    } else {
        // Create a new admin account
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role)
             VALUES (?, ?, ?, "admin")'
        );

        $insert->execute([
            $name,
            $email,
            $passwordHash
        ]);

        echo "Success: New admin account created.\n";
        echo "User ID: " . $pdo->lastInsertId() . "\n";
    }

} catch (PDOException $e) {
    // Avoid displaying database credentials or internal details
    error_log("Admin creation database error: " . $e->getMessage());
    exit("Error: Unable to create or update the admin account. Check the database connection.\n");
}
