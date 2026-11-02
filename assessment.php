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
    <link rel="stylesheet" href="css/assessment.css">
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
                <h1>Placement Readiness</h1>
                <p>
                    Assess your aptitude and technical skills.
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
        <section class="assessment-intro">
            <p>
                Evaluate your aptitude and technical knowledge through
                structured assessments designed around common placement
                requirements.
            </p>
        </section>
        <section class="assessment-options">
            <div class="assessment-card">
                <div class="assessment-icon">
                    <i data-lucide="calculator"></i>
                </div>
                <div class="assessment-content">
                    <h3>Aptitude</h3>
                    <p>
                        Evaluate your quantitative, logical and
                        verbal reasoning abilities.
                    </p>
                    <div class="assessment-topics">
                        <span>Quantitative</span>
                        <span>Logical</span>
                        <span>Verbal</span>
                    </div>
                </div>
                <a href="assessment-setup.php?type=aptitude" class="assessment-btn">
                Start Assessment
                <i data-lucide="arrow-right"></i>
                </a>
            </div>
            <div class="assessment-card">
                <div class="assessment-icon">
                    <i data-lucide="code-2"></i>
                </div>
                <div class="assessment-content">
                    <h3>Technical</h3>
                    <p>
                        Test your understanding of core computer
                        science and programming concepts.
                    </p>
                    <div class="assessment-topics">
                        <span>Programming</span>
                        <span>DBMS</span>
                        <span>OS</span>
                        <span>OOP</span>
                        <span>DSA</span>
                    </div>
                </div>
                <a href="assessment-setup.php?type=technical" class="assessment-btn">
                Start Full Test
                <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </section>
        <section class="full-assessment">
            <div class="full-assessment-content">
                <div class="full-assessment-icon">
                    <i data-lucide="layers-3"></i>
                </div>
                <div>
                    <h3>Full Placement Assessment</h3>
                    <p>
                        Take a combined assessment covering both
                        aptitude and technical skills.
                    </p>
                </div>
            </div>
            <a href="assessment-setup.php?type=full" class="full-assessment-btn">
            <span>Start Full Test</span>
            <i data-lucide="arrow-right"></i>
            </a>
        </section>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>