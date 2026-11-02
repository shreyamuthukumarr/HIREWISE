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
    <title>Assessment Setup | HireWise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assessment-setup.css">
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
                <h1>Assessment Setup</h1>
                <p>
                    Customize your placement assessment.
                </p>
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
                    <i
                        class="dropdown-icon"
                        data-lucide="chevron-down">
                    </i>
                </div>
            </div>
        </div>
        <section class="setup-wrapper">
            <div class="setup-card">
                <div class="setup-heading">
                    <div class="setup-icon">
                        <i data-lucide="sliders-horizontal"></i>
                    </div>
                    <div>
                        <h2>Configure Your Assessment</h2>
                        <p>
                            Choose the areas you want to practice
                            before starting your assessment.
                        </p>
                    </div>
                </div>
                <div class="setup-section">
                    <h3>Assessment Type</h3>
                    <div class="option-grid">
                        <label class="option-card">
                            <input
                                type="radio"
                                name="assessment-type"
                                value="mixed"
                                checked>
                            <div class="option-content">
                                <span class="option-title">
                                    Mixed Assessment
                                </span>
                                <span class="option-description">
                                    Questions from all selected areas.
                                </span>
                            </div>
                            <span class="radio-mark"></span>
                        </label>
                        <label class="option-card">
                            <input
                                type="radio"
                                name="assessment-type"
                                value="focused">
                            <div class="option-content">
                                <span class="option-title">
                                    Focused Practice
                                </span>
                                <span class="option-description">
                                    Concentrate on one specific topic.
                                </span>
                            </div>
                            <span class="radio-mark"></span>
                        </label>
                    </div>
                </div>
                <div class="setup-section" id="topic-section">
    <h3>Choose Topic</h3>

    <p class="setup-note" id="topic-description">
        Choose a topic for your assessment.
    </p>

    <div class="topic-grid" id="topic-grid">
        <!-- Topic options will be added by JavaScript -->
    </div>

    <p class="setup-error" id="topic-error" role="alert"></p>
</div>
                <div class="setup-section">
                    <h3>Difficulty</h3>
                    <div class="difficulty-options">
                        <label>
                            <input
                                type="radio"
                                name="difficulty"
                                value="easy">
                            <span>Easy</span>
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="difficulty"
                                value="medium">
                            <span>Medium</span>
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="difficulty"
                                value="mixed"
                                checked>
                            <span>Mixed</span>
                        </label>
                    </div>
                </div>
               <div class="setup-section question-section">

    <h3>Number of Questions</h3>

    <div class="question-count" id="question-count">

        <div class="count-options">

            <button
                type="button"
                class="count-btn"
                data-count="10"
                aria-pressed="false">
                10
            </button>

            <button
                type="button"
                class="count-btn active"
                data-count="20"
                aria-pressed="true">
                20
            </button>

            <button
                type="button"
                class="count-btn"
                data-count="30"
                aria-pressed="false">
                30
            </button>

        </div>

        <p class="setup-note">
            Questions will be selected dynamically from the
            HireWise question bank.
        </p>

    </div>

</div>
                <div class="setup-actions">
                    <a
                        href="assessment.php"
                        class="back-btn">
                        <i data-lucide="arrow-left"></i>
                        Back
                    </a>
                    <button type="button" class="begin-btn" id="begin-assessment">
                        Begin Assessment
                        <i data-lucide="arrow-right"></i>
                    </button>
                </div>
                <p class="setup-error"
   id="setup-error"
   role="alert"
   aria-live="polite"></p>
            </div>
        </section>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="js/assessment-setup.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>