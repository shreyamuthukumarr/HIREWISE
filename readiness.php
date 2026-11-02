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
    <title>Placement Readiness | HireWise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/readiness.css">
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
            <a href="assessment.php" class="nav-link">
                <i data-lucide="brain-circuit"></i>
                <span>Placement Assessment</span>
            </a>
            <a href="readiness.php" class="nav-link active">
                <i data-lucide="gauge"></i>
                <span>Placement Readiness</span>
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
            <a href="logout.php" class="nav-link">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Placement Readiness</h1>
                <p>See how ready you are, based on your resume and assessments.</p>
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

        <!-- Loading -->
        <section class="state-panel" id="loading-state">
            <div class="state-icon spin">
                <i data-lucide="loader-circle"></i>
            </div>
            <h2>Calculating your readiness&hellip;</h2>
            <p>Combining your resume insights with your assessment results.</p>
        </section>

        <!-- Error -->
        <section class="state-panel hidden" id="error-state">
            <div class="state-icon error">
                <i data-lucide="circle-alert"></i>
            </div>
            <h2>Couldn't calculate readiness</h2>
            <p id="error-message">Something went wrong.</p>
            <button type="button" class="ghost-btn" id="retry-btn">
                <i data-lucide="rotate-ccw"></i>
                Try again
            </button>
        </section>

        <!-- Needs data: something is missing -->
        <section class="needs-data hidden" id="needs-data-state">
            <div class="needs-data-header">
                <div class="state-icon">
                    <i data-lucide="target"></i>
                </div>
                <h2>A few things first</h2>
                <p>
                    Your readiness is calculated from both your resume and your
                    assessment results, so we need each of them before we can
                    give you an honest result.
                </p>
            </div>
            <div class="todo-list" id="todo-list">
                <!-- Filled by JS -->
            </div>
        </section>

        <!-- Result -->
        <section class="readiness-wrapper hidden" id="result-state">
            <div class="readiness-hero" id="readiness-hero">
                <div class="readiness-label-block">
                    <p class="hero-eyebrow">Your placement readiness</p>
                    <h2 class="readiness-label" id="readiness-label">Medium</h2>
                    <p class="hero-confidence" id="hero-confidence"></p>
                    <p class="hero-basis" id="hero-basis"></p>
                </div>
                <div class="probability-block">
                    <p class="block-title">Model confidence</p>
                    <div class="probability-list" id="probability-list">
                        <!-- Filled by JS -->
                    </div>
                </div>
            </div>

            <div class="insight-grid">
                <div class="insight-card">
                    <h3>Resume profile</h3>
                    <div class="metric-list" id="resume-metrics"></div>
                </div>
                <div class="insight-card">
                    <h3>Assessment performance</h3>
                    <div class="metric-list" id="assessment-metrics"></div>
                </div>
            </div>

            <div class="insight-card improve-card">
                <h3>Where to improve</h3>
                <div class="improve-list" id="improve-list"></div>
            </div>

            <div class="model-note">
                <i data-lucide="info"></i>
                <p id="model-note"></p>
            </div>

            <div class="page-actions">
                <a href="assessment.php" class="ghost-btn">
                    <i data-lucide="brain-circuit"></i>
                    Take another assessment
                </a>
                <a href="resume.php" class="ghost-btn">
                    <i data-lucide="file-text"></i>
                    Review my resume
                </a>
            </div>
        </section>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/readiness.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>