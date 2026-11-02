<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireWise - User Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css?v=2">
</head>
<body>
<div class="admin-shell">

    <div class="admin-topbar">
        <div class="admin-topbar-left">
            <div class="admin-logo">
                <i data-lucide="shield-check"></i>
                <h2>HireWise Admin</h2>
            </div>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-user-chip">
                <i data-lucide="user"></i>
                <span><?= htmlspecialchars($user['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" id="logoutBtn" class="admin-logout-btn">
                <i data-lucide="log-out"></i>
                Logout
            </button>
        </div>
    </div>

    <main class="admin-main">
        <a href="admin.php" class="admin-back-link">
            <i data-lucide="arrow-left"></i>
            Back to Admin Dashboard
        </a>

        <div class="admin-page-header">
            <h1>User Management</h1>
            <p>View all registered users. Change roles or activate/deactivate accounts.</p>
        </div>

        <div class="admin-status-banner" id="usersStatusBanner" hidden></div>

        <div class="admin-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Rows rendered by js/admin-users.js -->
                </tbody>
            </table>
            <div class="admin-empty-note" id="usersEmptyNote" hidden>
                No users found.
            </div>
        </div>
    </main>

</div>

<!-- Exposes the logged-in admin's own user_id to admin-users.js, so
     it can disable self-modification controls client-side (the
     server already blocks this in api/admin-update-user.php - this
     is just so the admin sees a disabled button instead of an
     error message after clicking). -->
<script>
    window.CURRENT_ADMIN_ID = <?= (int) $user['user_id'] ?>;
</script>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/script.js"></script>
<script src="js/admin-users.js?v=1"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>