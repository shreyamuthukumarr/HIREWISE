<?php
/**
 * HireWise - Session & Authentication Helper Functions
 * Phase 1 Implementation
 */

/**
 * Starts a session safely with secure cookie parameters if not already active.
 */
function start_session_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce secure session cookie settings
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        session_set_cookie_params($cookieParams);
        session_start();
    }
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool
 */
function is_logged_in(): bool {
    start_session_safe();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Returns current authenticated user metadata or null if guest.
 *
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'user_id' => (int)$_SESSION['user_id'],
        'name'    => $_SESSION['name'] ?? '',
        'email'   => $_SESSION['email'] ?? '',
        'role'    => $_SESSION['role'] ?? 'student'
    ];
}

/**
 * Checks if the currently logged in user has a specific role.
 *
 * @param string $role ('student' or 'admin')
 * @return bool
 */
function has_role(string $role): bool {
    $user = current_user();
    return $user !== null && strtolower($user['role']) === strtolower($role);
}

/**
 * Enforces that a request must be authenticated.
 * If not authenticated:
 *  - Returns HTTP 401 JSON if called via API / Fetch.
 *  - Redirects to login.html if called via direct page request.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
              || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized. Please login to access this resource.'
            ]);
            exit;
        }

        // Direct browser navigation redirect
        header('Location: login.html');
        exit;
    }
}

/**
 * Enforces that a request must be made by an administrator.
 */
function require_admin(): void {
    require_login();

    if (!has_role('admin')) {
        $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden. Administrator privileges required.'
            ]);
            exit;
        }

        header('Location: dashboard.html');
        exit;
    }
}

/**
 * Authenticates user into session.
 * Regenerates session ID to prevent session fixation.
 *
 * @param array $user User database record
 */
function login_user(array $user): void {
    start_session_safe();
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['login_time'] = time();
}

/**
 * Destroys current session and expires session cookie.
 */
function logout_user(): void {
    start_session_safe();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
