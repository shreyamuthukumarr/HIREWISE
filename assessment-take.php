<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Assessment | HireWise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assessment-take.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i data-lucide="briefcase-business"></i>
            </div>
            <div class="logo-text">
                <h2>HireWise</h2>
            </div>
        </div>
        <div class="nav-group">
            <p class="nav-title">OVERVIEW</p>
            <a href="dashboard.html" class="nav-link">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-group">
            <p class="nav-title">MODULES</p>
            <a href="resume.php" class="nav-link">
                <i data-lucide="file-text"></i>
                <span>Resume Intelligence</span>
            </a>
            <a href="assessment.php" class="nav-link active">
                <i data-lucide="brain-circuit"></i>
                <span>Placement Assessment</span>
            </a>
            <a href="recruitment.php" class="nav-link">
                <i data-lucide="shield-check"></i>
                <span>Recruitment Analysis</span>
            </a>
            <a href="offers.php" class="nav-link">
                <i data-lucide="briefcase"></i>
                <span>Offer Comparison</span>
            </a>
        </div>
        <div class="nav-group">
            <p class="nav-title">ACCOUNT</p>
            <a href="#" class="nav-link">
                <i data-lucide="user-round"></i>
                <span>Profile</span>
            </a>
            <a href="index.html" class="nav-link">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Take Assessment</h1>
                <p>Answer every question, then submit when you're ready.</p>
            </div>
            <div class="topbar-right">
                <div class="user-profile">
                    <div class="avatar">
                        <i data-lucide="user"></i>
                    </div>
                    <div class="user-details">
                                                                                      <h4>
    <?= htmlspecialchars($user['name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>
</h4>
<p>
    <?= htmlspecialchars(ucfirst($user['role'] ?? 'student'), ENT_QUOTES, 'UTF-8') ?>
</p>
                    </div>
                    <i class="dropdown-icon" data-lucide="chevron-down"></i>
                </div>
            </div>
        </div>

        <!-- Shown while questions are being fetched -->
        <section class="state-panel" id="loading-state">
            <div class="state-icon spin">
                <i data-lucide="loader-circle"></i>
            </div>
            <h2>Preparing your assessment&hellip;</h2>
            <p>Pulling questions that match your selected setup.</p>
        </section>

        <!-- Shown if something goes wrong (no config, API error, not enough questions) -->
        <section class="state-panel hidden" id="error-state">
            <div class="state-icon error">
                <i data-lucide="circle-alert"></i>
            </div>
            <h2>Couldn't start this assessment</h2>
            <p id="error-message">Something went wrong.</p>
            <a href="assessment-setup.php" class="back-to-setup-btn">
                <i data-lucide="arrow-left"></i>
                Back to setup
            </a>
        </section>

        <!-- The actual quiz -->
        <section class="quiz-wrapper hidden" id="quiz-wrapper">
            <div class="quiz-summary" id="quiz-summary">
                <!-- Filled in by JS: category / topic / difficulty / count -->
            </div>

            <div class="question-list" id="question-list">
                <!-- Question cards inserted here by JS -->
            </div>
        </section>
    </main>

    <!-- Sticky submit bar, only visible once the quiz has loaded -->
    <div class="submit-bar hidden" id="submit-bar">
        <div class="submit-progress">
            <span id="answered-count">0</span> of <span id="total-count">0</span> answered
        </div>
        <button type="button" class="submit-btn" id="submit-btn">
            Submit Assessment
            <i data-lucide="arrow-right"></i>
        </button>
    </div>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/assessment-take.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>