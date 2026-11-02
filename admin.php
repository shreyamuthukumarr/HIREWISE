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
    <title>HireWise - Admin Dashboard</title>
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
        <div class="admin-page-header">
            <h1>Admin Dashboard</h1>
            <p>Manage registered users and the placement readiness question bank.</p>
        </div>

        <div class="admin-nav-grid">
            <a href="admin-users.php" class="admin-nav-card">
                <div class="admin-nav-icon">
                    <i data-lucide="users"></i>
                </div>
                <div class="admin-nav-content">
                    <h3>User Management</h3>
                    <p>View registered students and admins, change roles, and activate or deactivate accounts.</p>
                </div>
                <i data-lucide="arrow-right" class="admin-nav-arrow"></i>
            </a>

            <a href="admin-questions.php" class="admin-nav-card">
                <div class="admin-nav-icon">
                    <i data-lucide="database"></i>
                </div>
                <div class="admin-nav-content">
                    <h3>Question Bank</h3>
                    <p>Add, edit, or remove aptitude and technical questions used in the placement assessment.</p>
                </div>
                <i data-lucide="arrow-right" class="admin-nav-arrow"></i>
            </a>
        </div>
    </main>

</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/script.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>