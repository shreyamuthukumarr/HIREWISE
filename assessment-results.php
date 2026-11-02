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
    <title>Assessment Results | HireWise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assessment-results.css">
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
                <h1>Assessment Results</h1>
                <p>Review your score and answer breakdown.</p>
            </div>
            <div class="topbar-right">
                <button class="notification-btn">
                    <i data-lucide="bell"></i>
                </button>
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

        <!-- Loading state -->
        <section class="state-panel" id="loading-state">
            <div class="state-icon spin">
                <i data-lucide="loader-circle"></i>
            </div>
            <h2>Loading your results&hellip;</h2>
        </section>

        <!-- Error state -->
        <section class="state-panel hidden" id="error-state">
            <div class="state-icon error">
                <i data-lucide="circle-alert"></i>
            </div>
            <h2>Couldn't load results</h2>
            <p id="error-message">Something went wrong.</p>
            <a href="assessment.html" class="back-to-setup-btn">
                <i data-lucide="arrow-left"></i>
                Back to Assessments
            </a>
        </section>

        <!-- Results -->
        <section class="results-wrapper hidden" id="results-wrapper">
            <div class="score-card">
                <div class="score-circle">
                    <span id="score-percentage">0%</span>
                </div>
                <div class="score-details">
                    <h2 id="score-headline">Assessment Complete</h2>
                    <p id="score-subtext">You got 0 of 0 correct.</p>
                    <div class="score-tags" id="score-tags">
                        <!-- category / topic / difficulty tags inserted by JS -->
                    </div>
                </div>
                <div class="score-actions">
    <a href="readiness.php" class="retake-btn">
        <i data-lucide="gauge"></i>
        View Placement Readiness
    </a>
    <a href="assessment.php" class="retake-btn">
        <i data-lucide="rotate-ccw"></i>
        Take Another Assessment
    </a>
</div>
            </div>

            <h3 class="breakdown-heading">Answer Breakdown</h3>

            <div class="question-breakdown-list" id="question-breakdown-list">
                <!-- Question review cards inserted by JS -->
            </div>
        </section>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/assessment-results.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>